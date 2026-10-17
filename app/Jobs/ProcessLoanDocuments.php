<?php

namespace App\Jobs;

use App\Models\LoanApplication;
use App\Models\Application;
use App\Services\CreditScoringService;
use App\Services\DocumentScannerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLoanDocuments implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public function __construct(
        public int $loanApplicationId,
        public array $documentPaths,
        public float $declaredIncome,
        public float $requestedAmount
    ) {}

    public function handle(DocumentScannerService $scanner, CreditScoringService $scorer)
    {
        $loanApp = LoanApplication::find($this->loanApplicationId);
        if (!$loanApp) return;

        $bestExtraction = null;
        $bestConfidence = 0;

        // Scan each uploaded document; keep the highest-confidence result
        foreach ($this->documentPaths as $path) {
            $fullPath = storage_path('app/public/' . $path);
            $extraction = $scanner->analyzeDocument($fullPath);

            if (($extraction['match_confidence'] ?? 0) > $bestConfidence) {
                $bestConfidence = $extraction['match_confidence'] ?? 0;
                $bestExtraction = $extraction;
            }
        }

        if (!$bestExtraction) {
            $loanApp->update([
                'document_processing_status' => 'failed',
                'ai_analysis_notes'          => 'No documents were uploaded.',
            ]);
            return;
        }

        // Determine document validation score based on extraction
        $documentScore = 40; // Default: mismatch (Scenario C)
        if (!empty($bestExtraction['is_legible']) && $bestExtraction['is_valid_document'] ?? false) {
            // Check for Scenario B: Matched Educational Documents
            $hasEnrollment = !empty($bestExtraction['education_purpose'])
                || (isset($bestExtraction['accepted_proof_type']) && in_array(
                    strtolower($bestExtraction['accepted_proof_type']),
                    ['certificate of enrollment', 'enrollment form', 'registration form', 'officially enrolled', 'student id', 'school year', 'school registration form', 'school report card']
                ));
            $hasTuition = (isset($bestExtraction['accepted_proof_type']) && in_array(
                    strtolower($bestExtraction['accepted_proof_type']),
                    ['statement of account', 'tuition fee breakdown', 'tuition fee statement', 'billing', 'tuition fee', 'school materials list', 'official list of books', 'list of books', 'school supplies list']
                ));

            // Name match check - replicates CreditScoringService::evaluateAndScan logic
            $applicantName = $loanApp->full_name ?? '';
            $normalizedOcrText = mb_strtolower($bestExtraction['raw_text'] ?? '');
            $normalizedApplicant = mb_strtolower(trim($applicantName));
            $applicantNoSpaces = preg_replace('/\s+/', '', $normalizedApplicant);
            $nameMatch = str_contains($normalizedOcrText, $normalizedApplicant) || str_contains($normalizedOcrText, $applicantNoSpaces);

            if ($hasEnrollment && $hasTuition) {
                $documentScore = 85; // Scenario B
            }
        } elseif (empty($bestExtraction['is_legible']) || !$bestExtraction['is_valid_document'] ?? false) {
            $documentScore = 30; // Scenario A: No docs or unreadable
        }

        // Use evaluateCapacity with document score for unified 0-100 scoring
        $evaluation = $scorer->evaluateCapacity(
            $this->requestedAmount,
            $this->declaredIncome,
            $bestExtraction,
            $documentScore
        );

        $loanApp->update([
            'ai_score'                   => $evaluation['score'],
            'ai_analysis_notes'          => $evaluation['summary'],
            'extracted_document_data'    => json_encode($bestExtraction),
            'document_processing_status' => 'completed',
        ]);

        // Mirror the AI score into the admin-side applications table
        Application::where('loan_application_id', $loanApp->id)->update([
            'ai_score' => $evaluation['score'],
        ]);
    }
}