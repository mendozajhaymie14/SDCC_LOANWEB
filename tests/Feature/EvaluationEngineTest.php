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
            'approved_credit_limit'    => 999999,
            // Manual monthly allowance high enough that the monthly gate never
            // binds for these engine-scoring samples; monthly cap tests live
            // in MonthlyCreditLimitTest.
            'approved_monthly_credit_limit' => 999999,
            'monthly_credit_limit_source'   => 'manual',
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
            // 1. Medium risk — comfortable income, savings, documents.
            //    EMI 50000 @ 12m = 4442; DTI = (5000+4442)/30000 = 0.315.
            //    Score: DTI 30 + payment 18 (no_history=18) + docs 0 + savings 5 (20%) + monthly limit 15 = 68 → Medium.
            'medium risk' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 5000,
                 'savings_balance' => 10000],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12],
                50000,
                'Medium',
            ],

            // 2. Medium risk — the request is more than twice the recommendation.
            //    Utility loan capped at 10000; DTI within limit.
            //    Score: DTI 30 + payment 18 + docs 0 + savings 0 + monthly limit 15 = 63 → Medium.
            'medium risk (request above 2x recommended)' => [
                ['monthly_income' => 20000, 'existing_monthly_obligations' => 2000,
                 'savings_balance' => 0],
                ['loan_type' => 'utility', 'amount' => 30000, 'term_months' => 12],
                10000,
                'Medium',
            ],

            // 3. High risk — over-obligated: obligations eat the whole DTI budget.
            //    Score: DTI 6 + payment 18 + docs 0 + savings 0 + monthly limit 15 = 39 → High.
            //    affordable = 500 / 0.0888487 ≈ 5627.54 (exact, no round-down).
            'high risk (over-obligated)' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 10000,
                 'savings_balance' => 0],
                ['loan_type' => 'regular', 'amount' => 100000, 'term_months' => 12,
                 'documents' => []],
                5627.54,
                'High',
            ],

            // 4. No payment history, no documents — neutral history score (18 pts), zero doc points.
            //    Educational product caps at the credit limit (₱999,999), so the
            //    full ₱20,000 request stands. DTI budget is only ₱500/month,
            //    but educational does not apply DTI-based affordability — the
            //    monthly gate is now the peso allowance (manual 999,999 here).
            //    Score: DTI 26 + payment 18 + docs 0 + savings 0 + monthly limit 15 = 59 → Medium.
            'no payment history (cannot afford)' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 10000,
                 'savings_balance' => 0],
                ['loan_type' => 'educational', 'amount' => 20000, 'term_months' => 12,
                 'documents' => []],
                20000,
                'Medium',
            ],

            // 5. Zero savings — no equity behind the loan, documents on file.
            //    Score: DTI 30 + payment 18 + docs 0 + savings 0 + monthly limit 15 = 63 → Medium.
            'zero savings' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 0,
                 'savings_balance' => 0],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12],
                50000,
                'Medium',
            ],

            // 6. Request above capacity — affordable principal caps the recommendation.
            //    No documents so docScore=0. Score: DTI 22 + payment 18 + docs 0 + savings 0 + monthly limit 15 = 55 → Medium.
            //    affordable = 7000 / 0.0888487 ≈ 78785.54 (exact, no round-down).
            'request above capacity' => [
                ['monthly_income' => 20000, 'existing_monthly_obligations' => 0,
                 'savings_balance' => 0],
                ['loan_type' => 'regular', 'amount' => 100000, 'term_months' => 12,
                 'documents' => []],
                78785.54,
                'Medium',
            ],

            // 7. Missing documents — no doc points, everything else healthy.
            //    Score: DTI 30 + payment 18 + docs 0 + savings 5 (20%) + monthly limit 15 = 68 → Medium.
            'missing documents' => [
                ['monthly_income' => 30000, 'existing_monthly_obligations' => 5000,
                 'savings_balance' => 10000],
                ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12,
                 'documents' => []],
                50000,
                'Medium',
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

        // The score is the sum of the five configured factors.
        $details = $result['details'];
        $this->assertSame(
            $details['dti_score'] + $details['payment_score'] + $details['doc_score'] + $details['savings_score'] + $details['monthly_limit_score'],
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
        ]);
        $loanApp = $this->apply($member, ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12]);

        $result = app(LoanEvaluationService::class)->evaluate($loanApp);
        $details = $result['details'];

        // 30 (DTI) + 18 (no history) + 0 (docs weight 0) + 5 (savings minimal 20% of 50k) + 15 (request fits manual monthly limit) = 68 → Medium
        $this->assertSame(30, $details['dti_score']);
        $this->assertSame(18, $details['payment_score']);
        $this->assertSame(0, $details['doc_score']);
        $this->assertSame(5, $details['savings_score']);
        $this->assertSame(15, $details['monthly_limit_score']);
        $this->assertSame(68, $result['risk_score']);
        $this->assertSame('Medium', $result['risk_level']);
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
            ]);
            $loanApp = $this->apply($member, ['loan_type' => 'regular', 'amount' => 50000, 'term_months' => 12]);

            $result = app(LoanEvaluationService::class)->evaluate($loanApp);

            // 20 (DTI) + 18 (history) + 0 (docs weight 0) + 5 (savings) + 15 (monthly limit) = 58 → Medium band (50–69)
            $this->assertSame(58, $result['risk_score']);
            $this->assertSame('Medium', $result['risk_level']);
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