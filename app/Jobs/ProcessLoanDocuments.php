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

        $evaluation = $scorer->evaluateCapacity(
            $this->requestedAmount,
            $this->declaredIncome,
            $bestExtraction
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