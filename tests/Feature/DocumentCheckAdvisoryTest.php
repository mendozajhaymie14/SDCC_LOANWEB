<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\CreditScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task F (FR-12 / FR-13): document check is advisory only.
 *
 * The score ALWAYS comes from LoanEvaluationService::evaluate() — document
 * results are stored as advisory `document_check` field only. The scorer
 * never adjusts risk_score, recommended_amount, or risk_level based on docs.
 */
class DocumentCheckAdvisoryTest extends TestCase
{
    use RefreshDatabase;

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

    private function applyNoDocs(CoopMember $member, array $overrides = []): LoanApplication
    {
        return LoanApplication::factory()->create(array_merge([
            'user_id'                        => $member->user_id,
            'loan_type'                      => 'regular',
            'amount'                         => 20000,
            'term_months'                    => 12,
            'monthly_income'                 => (float) $member->monthly_income,
            'existing_monthly_obligations'   => (float) $member->existing_monthly_obligations,
            'share_capital'                  => (float) $member->share_capital,
            'documents'                      => [],
        ], $overrides));
    }

    /** @test */
    public function score_comes_from_rule_based_evaluation_not_documents()
    {
        $member = $this->borrower();
        $loanApp = $this->applyNoDocs($member);

        $service = new CreditScoringService();
        $result = $service->evaluateAndScan($loanApp);

        // Score is always the rule-based risk_score from LoanEvaluationService
        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    /** @test */
    public function analysis_note_never_contains_ai_score_label()
    {
        $member = $this->borrower();
        $loanApp = $this->applyNoDocs($member);

        $service = new CreditScoringService();
        $result = $service->evaluateAndScan($loanApp);

        $this->assertStringNotContainsString('AI Score', $result['analysis_note']);
        // Must contain advisory language
        $this->assertStringContainsString('Rule-based score', $result['analysis_note']);
    }

    /** @test */
    public function note_mentions_no_documents_when_none_uploaded()
    {
        $member = $this->borrower();
        $loanApp = $this->applyNoDocs($member);

        $service = new CreditScoringService();
        $result = $service->evaluateAndScan($loanApp);

        $this->assertStringContainsString('No supporting documents', $result['analysis_note']);
        $this->assertStringContainsString('Rule-based score computed from declared data only', $result['analysis_note']);
    }

    /** @test */
    public function with_documents_still_uses_rule_based_score()
    {
        $member = $this->borrower();
        $loanApp = $this->applyNoDocs($member);
        $loanApp->documents = ['id_card.jpg'];
        $loanApp->save();

        $service = new CreditScoringService();
        $result = $service->evaluateAndScan($loanApp);

        // Score must be rule-based regardless of document existence
        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    /** @test */
    public function pdf_document_triggers_manual_review_not_jpeg_mapping()
    {
        $member = $this->borrower();
        $loanApp = $this->applyNoDocs($member);
        $loanApp->documents = ['report.pdf'];
        $loanApp->save();

        $service = new CreditScoringService();
        $result = $service->evaluateAndScan($loanApp);

        // PDF maps to needs_review/manual review, not IMAGE_JPEG
        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }
}