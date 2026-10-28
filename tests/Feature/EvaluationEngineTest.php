<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task E (FR-10 / FR-11): the rule engine.
 *
 * Every sample below is a fixed borrower — income, obligations, savings and
 * documents are pinned so the expected recommended amount and risk level are
 * exact, never dependent on the randomised factory values.
 */
class EvaluationEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A member with a fully pinned financial profile.
     */
    private function borrower(array $overrides = []): CoopMember
    {
        $user = User::factory()->create(['usertype' => 'member']);

        $member = CoopMember::factory()->create(array_merge([
            'user_id'                  => $user->id,
            'status'                   => CoopMember::STATUS_ACTIVE,
            'monthly_income'           => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance'          => 0,
            'share_capital'            => 30000,
            'approved_credit_limit'    => 50000,
            'is_migs'                  => false,
        ], $overrides));

        $user->forceFill(['coop_member_id' => $member->id])->save();

        return $member->fresh();
    }

    /**
     * A loan application pinned to the borrower's figures.
     */
    private function apply(CoopMember $member, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'                        => $member->user_id,
            'loan_type'                      => 'regular',
            'amount'                         => 20000,
            'term_months'                    => 12,
            'monthly_income'                 => (float) $member->monthly_income,
            'existing_monthly_obligations'   => (float) $member->existing_monthly_obligations,
            'share_capital'                  => (float) $member->share_capital,
            'documents'                      => ['id.pdf', 'income.pdf'],
        ], $overrides));
    }

    /**
     * The eight fixed sample borrowers. Each row is a positional array:
     * [member overrides, application overrides, expected amount, expected risk].
     */
    public static function sampleProvider(): array
    {
        return [
            // 1. Low risk — comfortable income, savings, documents.
            'low risk' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 5000,
                 'savings_balance' => 10000, 'share_capital' => 50000],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12],
                50000,
                'Low',
            ],

            // 2. Medium risk — the request is more than twice the recommendation.
            'medium risk (request above 2x recommended)' => [
                ['monthly_income' => 20000, 'existing_monthly_obligations' => 2000,
                 'savings_balance' => 0, 'share_capital' => 50000],
                ['loan_type' => 'utility', 'amount' => 30000, 'term_months' => 12],
                10000,
                'Medium',
            ],

            // 3. High risk — over-obligated: obligations eat the whole DTI budget.
            'high risk (over-obligated)' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 10000,
                 'savings_balance' => 0, 'share_capital' => 50000],
                ['loan_type' => 'regular', 'amount' => 100000, 'term_months' => 12,
                 'documents' => []],
                5000,
                'High',
            ],

            // 4. No payment history, no documents, nothing affordable —
            //    neutral history score, zero doc points, and a negative DTI budget.
            'no payment history (cannot afford)' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 10000,
                 'savings_balance' => 0, 'share_capital' => 30000],
                ['loan_type' => 'educational', 'amount' => 20000, 'term_months' => 12,
                 'documents' => []],
                5000,
                'High',
            ],

            // 5. Zero savings — no equity behind the loan, documents on file.
            'zero savings' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 0,
                 'savings_balance' => 0, 'share_capital' => 30000],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12],
                50000,
                'Low',
            ],

            // 6. Request above capacity — affordable principal caps the recommendation.
            'request above capacity' => [
                ['monthly_income' => 20000, 'existing_monthly_obligations' => 0,
                 'savings_balance' => 0, 'share_capital' => 30000],
                ['loan_type' => 'regular', 'amount' => 100000, 'term_months' => 12],
                78000,
                'High',
            ],

            // 7. Missing documents — no doc points, everything else healthy.
            'missing documents' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 5000,
                 'savings_balance' => 10000, 'share_capital' => 50000],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12,
                 'documents' => []],
                50000,
                'Medium',
            ],

            // 8. Recovery loan — MIGS member gets the higher share-capital rate.
            'recovery loan (MIGS)' => [
                ['monthly_income' => 20000, 'existing_monthly_obligations' => 2000,
                 'savings_balance' => 50000, 'share_capital' => 50000, 'is_migs' => true],
                ['loan_type' => 'recovery', 'amount' => 25000, 'term_months' => 24],
                25000,
                'Low',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider sampleProvider
     */
    public function the_engine_evaluates_each_fixed_sample_borrower_exactly(
        array $memberOverrides,
        array $applicationOverrides,
        float $expectedAmount,
        string $expectedRisk
    ) {
        $label = $this->dataName();
        $member = $this->borrower($memberOverrides);
        $loanApp = $this->apply($member, $applicationOverrides);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);

        $this->assertEquals(
            $expectedAmount,
            (float) $result['recommended_amount'],
            "[{$label}] recommended amount"
        );
        $this->assertSame(
            $expectedRisk,
            $result['risk_level'],
            "[{$label}] risk level"
        );

        // The recommendation never exceeds what was requested.
        $this->assertLessThanOrEqual(
            (float) $loanApp->amount,
            (float) $result['recommended_amount'],
            "[{$label}] recommended amount exceeded the requested amount"
        );

        // The score is the sum of the four configured factors.
        $details = $result['details'];
        $this->assertSame(
            $details['dti_score'] + $details['payment_score'] + $details['doc_score'] + $details['savings_score'],
            $result['risk_score'],
            "[{$label}] score is not the sum of the per-factor points"
        );
    }

    /** @test */
    public function the_breakdown_reports_the_formula_and_the_per_factor_points()
    {
        $member = $this->borrower([
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
            'savings_balance' => 10000,
            'share_capital' => 50000,
        ]);
        $loanApp = $this->apply($member, ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);
        $details = $result['details'];

        // 40 (DTI) + 20 (no history) + 15 (docs) + 5 (savings covers 20 %) = 80 → Low
        $this->assertSame(40, $details['dti_score']);
        $this->assertSame(20, $details['payment_score']);
        $this->assertSame(15, $details['doc_score']);
        $this->assertSame(5, $details['savings_score']);
        $this->assertSame(80, $result['risk_score']);
        $this->assertSame('Low', $result['risk_level']);
    }

    /** @test */
    public function the_weights_and_thresholds_live_in_config_and_they_drive_the_score()
    {
        // Changing a weight in config changes the score — the engine holds no
        // hard-coded weights of its own.
        $originalDti = config('loan_evaluation.weights.dti');
        $originalLow = config('loan_evaluation.thresholds.risk_low');
        try {
            config()->set('loan_evaluation.weights.dti', 20);
            config()->set('loan_evaluation.thresholds.risk_low', 60);

            $member = $this->borrower([
                'monthly_income' => 30000,
                'existing_monthly_obligations' => 5000,
                'savings_balance' => 10000,
                'share_capital' => 50000,
            ]);
            $loanApp = $this->apply($member, ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12]);

            $result = app(LoanEvaluationService::class)->evaluate($loanApp);

            // 20 (DTI) + 20 (history) + 15 (docs) + 5 (savings) = 60 → still Low at 60
            $this->assertSame(60, $result['risk_score']);
            $this->assertSame('Low', $result['risk_level']);
        } finally {
            config()->set('loan_evaluation.weights.dti', $originalDti);
            config()->set('loan_evaluation.thresholds.risk_low', $originalLow);
        }
    }

    /**
     * NFR-03: the evaluation must answer in under two seconds with the Gemini
     * document scan switched off (the evaluation path never calls Gemini).
     *
     * @test
     */
    public function the_evaluation_endpoint_answers_in_under_two_seconds_with_gemini_disabled()
    {
        config()->set('gemini.api_key', null);

        $manager = User::factory()->create(['usertype' => 'branch_manager']);
        $owner   = User::factory()->create(['usertype' => 'member']);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $owner->id,
            'loan_type' => 'regular',
            'amount' => 50000,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'documents' => ['id.pdf'],
        ]);
        $application = Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id' => 'APP-9001',
            'applicant' => 'Timing Sample',
            'status' => 'pending',
            'amount' => 50000,
            'risk_score' => 80,
            'risk_level' => 'Low',
        ]);

        $start = microtime(true);
        $response = $this->actingAs($manager)
            ->get(route('admin.applications.data', $application->id));
        $elapsed = microtime(true) - $start;

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertLessThan(
            2.0,
            $elapsed,
            sprintf('The evaluation endpoint took %.3f s (budget 2.000 s).', $elapsed)
        );
    }
}
