<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Services\CreditScoringService;
use App\Services\DocumentScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLoanDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $loanApplicationId,
        public float $declaredIncome,
        public float $requestedAmount,
        public array $documentPaths = []
    ) {}

    public function handle(DocumentScannerService $scanner, CreditScoringService $scorer): void
    {
        $loanApp = LoanApplication::find($this->loanApplicationId);
        if (!$loanApp) {
            return;
        }

        try {
            $bestExtraction = null;
            $bestConfidence = -1;

            // Scan each uploaded document; keep the highest-confidence result
            foreach ($this->documentPaths as $path) {
                $fullPath = storage_path('app/public/' . $path);
                $extraction = $scanner->analyzeDocument($fullPath);

                $confidence = $extraction['match_confidence'] ?? 0;
                if ($confidence > $bestConfidence) {
                    $bestConfidence = $confidence;
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

            $isLegible = !empty($bestExtraction['is_legible']);
            $isValid   = !empty($bestExtraction['is_valid_document']);

            $documentScore = 40; // Scenario C: mismatch

            if ($isLegible && $isValid) {
                $proof = strtolower($bestExtraction['accepted_proof_type'] ?? '');

                $hasEnrollment = !empty($bestExtraction['education_purpose'])
                    || in_array($proof, [
                        'certificate of enrollment', 'enrollment form', 'registration form',
                        'officially enrolled', 'student id', 'school year',
                        'school registration form', 'school report card',
                    ], true);

                $hasTuition = in_array($proof, [
                    'statement of account', 'tuition fee breakdown', 'tuition fee statement',
                    'billing', 'tuition fee', 'school materials list',
                    'official list of books', 'list of books', 'school supplies list',
                ], true);

                if ($hasEnrollment && $hasTuition) {
                    $documentScore = 85; // Scenario B
                }
            } elseif (!$isLegible || !$isValid) {
                $documentScore = 30; // Scenario A: unreadable or invalid
            }

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
        } catch (\Throwable $e) {
            Log::warning('Document scan failed for loan application ' . $this->loanApplicationId . ': ' . $e->getMessage());

            $loanApp->update([
                'document_processing_status' => 'unverified',
                'ai_analysis_notes'          => 'Document scan failed (' . $e->getMessage()
                    . '). Score computed from declared data only; documents unverified.',
            ]);
        }
    }
}