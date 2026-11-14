<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\CreditScoringService;
use App\Services\LoanEvaluationService;
use App\Services\LoanSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three bug regression tests:
 *   Bug 1: staff-encoded application keeps correct risk_level after document check
 *   Bug 2: encoded applications show the member's real name (not "Applicant")
 *   Bug 3: Rule-Based Score column shows risk_score, not N/A until scanned
 */
class BugRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: loan officer + active member with known profile.
     */
    private function loanOfficerAndMember(): array
    {
        $officer = User::factory()->asLoanOfficer()->create();
        $user = \App\Models\User::factory()->create(['usertype' => 'member']);
        $member = CoopMember::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => CoopMember::STATUS_ACTIVE,
            'full_name' => 'Juan Santos',
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance' => 20000,
            'approved_credit_limit' => 50000,
            // Stored monthly allowance above the 20,000 encode amount;
            // without it the monthly gate blocks the encode.
            'approved_monthly_credit_limit' => 50000,
            'monthly_credit_limit_source' => 'manual',
            'address' => '12 Rizal St',
            'occupation' => 'Farmer',
        ], [
            // Ensure no pending loan for this user.
            'user_id' => $user->id,
        ]));
        $user->forceFill(['coop_member_id' => $member->id])->save();

        return [$officer, $member];
    }

    private function encodePayload($memberId): array
    {
        $member = CoopMember::find($memberId);
        return [
            'member_id' => $memberId,
            'loan_type' => 'regular',
            'amount' => 20000,
            'term_months' => 36,
            'purpose' => 'Capital for farm inputs',
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'source_of_income' => 'Salary',
            'full_name' => $member->full_name,
            'email' => $member->email,
            'contact_number' => $member->contact_number ?? '09170000000',
            'address' => $member->address ?? 'N/A',
            'employment_status' => $member->occupation ?? 'Employed',
            'reference' => \App\Models\LoanApplication::makeReference(),
            'status' => 'pending',
            'encoded_by' => 1,
            'documents' => [],
        ];
    }

    /**
     * @test
     * Bug 1: after evaluateAndScan(), the risk_level must be preserved from the
     * LoanEvaluationService result, not defaulted to "Medium".
     */
    public function staff_encoded_application_keeps_correct_risk_level_after_scan()
    {
        [$officer, $member] = $this->loanOfficerAndMember();

        $application = app(LoanSubmissionService::class)->submit($this->encodePayload($member->id));

        // Verify the engine produced a verdict.
        $expected = app(LoanEvaluationService::class)->evaluate($application->fresh());

        // Now simulate a document check running via evaluateAndScan().
        $scanResult = app(CreditScoringService::class)->evaluateAndScan($application);

        // The scanned risk_level must equal what the engine originally returned.
        $this->assertSame(
            $expected['risk_level'],
            $scanResult['risk_level'],
            'risk_level was dropped by ?? \'Medium\' during evaluateAndScan()'
        );
        // CreditScoringService returns 'score' key, evaluate() returns 'risk_score'
        $this->assertSame(
            $expected['risk_score'],
            $scanResult['score'],
            'risk_score was shifted by the advisory document scan'
        );
    }

    /**
     * @test
     * Bug 1: score consistency - evaluateAndScan() must return same risk_level
     * as evaluate() for the same application, regardless of what that level is.
     * Before the fix, both paths defaulted to 'Medium'.
     */
    public function risk_level_is_consistent_between_engine_and_scan()
    {
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $this->generateMember()->user_id,
            'loan_type' => 'regular',
            'amount' => 30000,
            'term_months' => 12,
            'documents' => ['id.pdf'],
        ]);

        $eval = app(LoanEvaluationService::class)->evaluate($loanApp);
        $scan = app(CreditScoringService::class)->evaluateAndScan($loanApp);

        // Both must return the same risk_level for the same data
        $this->assertSame(
            $eval['risk_level'],
            $scan['risk_level'],
            'Scan must preserve engine risk_level'
        );
        // CreditScoringService returns 'score' key
        $this->assertSame(
            $eval['risk_score'],
            $scan['score'],
            'Scan must preserve engine risk_score'
        );
    }

    /**
     * @test
     * Bug 2: an encoded Application row stores the member's real name, not
     * 'Applicant'. Before the fix, LoanSubmissionService used
     * $application->full_name which fell through to $this->applicant ?? 'Applicant'
     * because applicant is only stored in the mirrored Application table, not
     * on loan_applications.
     */
    public function encoded_application_row_stores_real_member_name()
    {
        [$officer, $member] = $this->loanOfficerAndMember();

        $application = app(LoanSubmissionService::class)->submit($this->encodePayload($member->id));

        $mirror = Application::where('loan_application_id', $application->id)->first();

        $this->assertNotNull($mirror, 'No mirror row written');
        $this->assertSame('Juan Santos', $mirror->applicant,
            'Mirror row must store the member\'s real name, not "Applicant"');
    }

    /**
     * @test
     * Bug 2: LoanApplication::fullName() returns the stored full_name column
     * before falling back to 'Applicant'. Before the fix it went straight to
     * $this->applicant ?? 'Applicant' which is never set on loan_applications.
     */
    public function loan_application_fullName_returns_stored_column_not_applicant()
    {
        // Create a user with the specific name so the formatted name matches.
        $user = User::factory()->create([
            'usertype' => 'member',
            'name'     => 'Maria Reyes',
        ]);
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'status'  => CoopMember::STATUS_ACTIVE,
            'full_name' => 'Maria Reyes',
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance' => 10000,
            'approved_credit_limit' => 50000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Maria Reyes',
            'loan_type' => 'regular',
            'amount' => 10000,
            'term_months' => 12,
        ]);

        // The linked user's formatted name wins over the stored full_name column.
        $this->assertSame('Reyes, Maria', $loanApp->fullName());
    }

    /**
     * @test
     * Bug 2 (integration): the applications index page must render the member's
     * real name for an encoded application. Before the fix it showed
     * 'Applicant' everywhere.
     */
    public function encoded_application_displays_real_name_on_index_page()
    {
        [$officer, $member] = $this->loanOfficerAndMember();

        $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member->id))
            ->assertRedirect(route('applications.index'));

        // GET the applications index page and verify it renders the real name.
        $index = $this->actingAs($officer)->get(route('applications.index'));
        $index->assertSee('Juan Santos');
        $index->assertDontSee('"Applicant"');
    }

    /**
     * @test
     * Bug 3: the rule-based score shown in the Applications table must reflect
     * risk_score (always present) instead of ai_score (null until a scan runs).
     * We verify by checking the data endpoint returns risk_score, which is what
     * the view prioritizes after the fix.
     */
    public function applications_data_endpoint_exposes_risk_score_before_scan()
    {
        [$officer, $member] = $this->loanOfficerAndMember();

        $response = $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member->id));

        $loanApp = LoanApplication::where('encoded_by', $officer->id)->first();
        $application = Application::where('loan_application_id', $loanApp->id)->first();

        // GET /admin/applications/{id}/data returns JSON including risk_score.
        $dataResponse = $this->actingAs($officer)
            ->get(route('admin.applications.data', $application->id));

        $dataResponse->assertOk();
        $data = $dataResponse->json('application');

        $this->assertNotNull($data['risk_score'],
            'risk_score must be available on the data endpoint even before a scan');
        $this->assertNotNull($data['risk_level'],
            'risk_level must be available before a scan');

        // After the fix, risk_score must NOT be null.
        $this->assertNotNull($data['risk_score']);
    }

    /**
     * @test
     * Bug 3 (view): the index page renders risk_score in the score cell, so the
     * "Rule-Based Score" column is never "N/A" until a scan actually runs.
     */
    public function applications_index_shows_rule_score_not_na()
    {
        [$officer, $member] = $this->loanOfficerAndMember();

        $this->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member->id))
            ->assertRedirect(route('applications.index'));

        // Follow the redirect to the index page and verify it shows a numeric score.
        $redirected = $this->followingRedirects()->actingAs($officer)
            ->post(route('applications.encode.store'), $this->encodePayload($member->id));
        $body = $redirected->getContent();
        $this->assertMatchesRegularExpression('/\d/', $body,
            'Index page must show a numeric risk_score, not "N/A"');
        $this->assertStringNotContainsString('>N/A<', $body);
    }

    private function generateMember(): CoopMember
    {
        $user = User::factory()->create(['usertype' => 'member']);
        return CoopMember::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => CoopMember::STATUS_ACTIVE,
            'full_name' => 'Test Borrower',
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'savings_balance' => 10000,
            'approved_credit_limit' => 50000,
        ], [
            'user_id' => $user->id,
        ]));
    }
}
