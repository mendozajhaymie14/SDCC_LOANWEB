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
            // Run the rule-based evaluation — single source of truth for
            // recommended_amount, risk_level and risk_score.
            $eval = app(\App\Services\LoanEvaluationService::class)->evaluate($loanApp);

            $bestExtraction = null;
            $bestConfidence = -1;

            // Scan each uploaded document; keep the highest-confidence result.
            // These results are ADVISORY only — they never change the score.
            foreach ($this->documentPaths as $path) {
                $fullPath = storage_path('app/public/' . $path);
                $extraction = $scanner->analyzeDocument($fullPath, $loanApp->loan_type);

                $confidence = $extraction['match_confidence'] ?? 0;
                if ($confidence > $bestConfidence) {
                    $bestConfidence = $confidence;
                    $bestExtraction = $extraction;
                }
            }

            $documentCheck = $bestExtraction && !empty($bestExtraction['is_legible']) && !empty($bestExtraction['is_valid_document'])
                ? 'passed' : 'needs_review';

            $loanApp->update([
                'document_processing_status' => 'completed',
                'ai_analysis_notes'          => 'Document check: ' . $documentCheck . '. Recommended amount and risk level are rule-based; AI document check is advisory.',
                'extracted_document_data'    => $bestExtraction ? json_encode($bestExtraction) : null,
                'risk_level'                 => $eval['risk_level'],
                'risk_score'                 => $eval['risk_score'],
                'recommended_amount'         => $eval['recommended_amount'],
            ]);

            // Mirror document status into the admin-side applications table
            Application::where('loan_application_id', $loanApp->id)->update([
                'risk_level'      => $eval['risk_level'],
                'risk_score'      => $eval['risk_score'],
                'recommended_amount' => $eval['recommended_amount'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document scan failed for loan application ' . $this->loanApplicationId . ': scan error');

            $loanApp->update([
                'document_processing_status' => 'unverified',
                'ai_analysis_notes'          => 'Document scan failed (' . $e->getMessage()
                    . '). Score computed from declared data only; documents unverified.',
            ]);
        }
    }
}