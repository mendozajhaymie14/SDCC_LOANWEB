<?php

namespace App\Services;

use App\Models\Application;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreditScoringService
{
    /**
     * Performs 3-tier AI document analysis scan on a LoanApplication or Application instance.
     * Evaluates uploaded documents against Educational Loan Proof of Purpose requirements
     * and returns a credit score with detailed notes.
     *
     * Scenarios:
     *   No documents        → base score (rule-based), documents unverified note
     *   Matched docs        → base score + doc component, truthful verification note
     *   Mismatched docs     → base score (rule-based), mismatch note
     *
     * The score always equals the LoanEvaluationService rule-based score (0-100).
     * Document analysis only adjusts the documents component (0-15 pts).
     *
     * @param LoanApplication|Application $loanApp
     * @param array|null $documents
     * @return array{score: int, analysis_note: string, has_risk_flags: bool, risk_level: string, risk_factors: array<int,string>, recommended_amount: float}
     */
    public function evaluateAndScan($loanApp, ?array $documents = null): array
    {
        // Accept documents from parameter or model attributes
        $documents = $documents ?? (is_array($loanApp->documents ?? null) ? $loanApp->documents : []);

        // Resolve applicant name and linked application safely
        $applicantName = trim($loanApp->full_name ?? $loanApp->applicant ?? '');
        $application = ($loanApp instanceof Application) ? $loanApp : ($loanApp->application ?? null);

        // Determine loan type for document verification rules
        $loanType = $loanApp->loan_type ?? 'regular';

        // Define document verification rules per loan type
        $eduRegistrationKeywords = [
            'Certificate of Enrollment',
            'Enrollment',
            'Officially Enrolled',
            'Student ID',
            'Registration',
            'School Year',
        ];

        $eduTuitionKeywords = [
            'Statement of Account',
            'Tuition Fee',
            'Billing',
            'Statement of Due',
            'Breakdown',
            'Total Due',
        ];

        $emergencyKeywords = [
            'Hospitalization',
            'Medical Certificate',
            'Doctor\'s Note',
            'Hospital Bill',
            'Medical Receipt',
            'Typhoon',
            'Flood',
            'Fire',
            'Earthquake',
            'Natural Disaster',
            'Accident Report',
            'Police Report',
            'Barangay Certificate',
            'Emergency',
            'Urgent',
            'Calamity',
            'Disaster Relief',
        ];

        // Compute the base rule-based score and also retrieve full evaluation
        // (reasons, recommended_amount, risk_level) so we can return them.
        $eval = $this->computeBaseScoreWithEvaluation($loanApp);

        // Scenario A: No uploaded documents — documents unverified, truthful note
        if (empty($documents)) {
            $score = $eval['risk_score'];
            $note = 'AI Scan: No supporting documents uploaded. Documents not verified automatically; manual review required. Rule-based score computed from declared data only.';
            $riskLevel = $eval['risk_level'] ?? 'Medium';
            $riskFactors = $eval['reasons'] ?? [];
            $this->persistScore($application, $loanApp, $score, $note, $riskLevel, $riskFactors);
            return [
                'score' => $score,
                'analysis_note' => $note,
                'has_risk_flags' => $score < 50,
                'risk_level' => $riskLevel,
                'risk_factors' => $riskFactors,
                'recommended_amount' => $eval['recommended_amount'] ?? 0.0
            ];
        }

        $scanner = new DocumentScannerService();
        $firstDoc = $documents[0] ?? null;

        if (!$firstDoc) {
            $score = $eval['risk_score'];
            $note = 'AI Scan: No supporting documents uploaded. Documents not verified automatically; manual review required. Rule-based score computed from declared data only.';
            $riskLevel = $eval['risk_level'] ?? 'Medium';
            $riskFactors = $eval['reasons'] ?? [];
            $this->persistScore($application, $loanApp, $score, $note, $riskLevel, $riskFactors);
            return [
                'score' => $score,
                'analysis_note' => $note,
                'has_risk_flags' => $score < 50,
                'risk_level' => $riskLevel,
                'risk_factors' => $riskFactors,
                'recommended_amount' => $eval['recommended_amount'] ?? 0.0
            ];
        }

        // Build the full absolute disk path for the document.
        $filePath = is_array($firstDoc) ? ($firstDoc['path'] ?? '') : (string) $firstDoc;
        if (!file_exists($filePath)) {
            $storagePath = storage_path('app/public/' . $filePath);
            if (file_exists($storagePath)) {
                $filePath = $storagePath;
            } else {
                $publicPath = public_path('storage/' . $filePath);
                if (file_exists($publicPath)) {
                    $filePath = $publicPath;
                }
            }
        }

        // Analyze the document using DocumentScannerService (Gemini Vision / OCR)
        $scanResult = $scanner->analyzeDocument($filePath ?? '', $loanType);

        // Document check is advisory only. When the scanner returned
        // unavailable (file missing / AI error) or manual_review (PDF / unsupported
        // type), the score stays rule-based and we do NOT write a mismatch note.
        $documentCheck = $scanResult['document_check'] ?? 'unavailable';
        if (in_array($documentCheck, ['unavailable', 'manual_review'])) {
            $score       = $eval['risk_score'];
            $riskLevel   = $eval['risk_level'] ?? 'Medium';
            $riskFactors = $eval['reasons'] ?? [];
            $note        = 'Document check unavailable, manual review required. '
                           . 'Rule-based score computed from declared data only.';

            $this->persistScore($application, $loanApp, $score, $note, $riskLevel, $riskFactors, $documentCheck);

            return [
                'score'            => $score,
                'analysis_note'    => $note,
                'document_check'   => $documentCheck,
                'has_risk_flags'   => ($score < 50) || ($documentCheck === 'needs_review'),
                'risk_level'       => $riskLevel,
                'risk_factors'     => $riskFactors,
                'recommended_amount' => $eval['recommended_amount'] ?? 0.0,
            ];
        }

        // Extract OCR text and validation data from the scan result
        $ocrText = $scanResult['raw_text'] ?? '';
        $acceptedProofType = $scanResult['accepted_proof_type'] ?? null;
        $isValidDocument = $scanResult['is_valid_document'] ?? false;

        // Normalize text for case-insensitive matching
        $normalizedOcrText = mb_strtolower($ocrText);
        $normalizedApplicant = mb_strtolower($applicantName);

        // 1. Check for Proof of Purpose — loan-type-aware
        $hasPrimaryProof = false;
        $hasSecondaryProof = false;
        $docSummary = '';

        if ($loanType === 'emergency') {
            foreach ($emergencyKeywords as $keyword) {
                if (str_contains($normalizedOcrText, mb_strtolower($keyword))) {
                    $hasPrimaryProof = true;
                    break;
                }
            }
            $emergencyProofTypes = [
                'hospitalization', 'medical certificate', 'hospital bill',
                'medical receipt', 'doctor\'s note', 'typhoon', 'flood', 'fire',
                'earthquake', 'natural disaster', 'accident report', 'police report',
                'barangay certificate', 'emergency', 'calamity', 'disaster relief',
            ];
            if (in_array(mb_strtolower($acceptedProofType ?? ''), $emergencyProofTypes)) {
                $hasPrimaryProof = true;
            }
            $hasSecondaryProof = $hasPrimaryProof;
            $docSummary = $hasPrimaryProof ? 'Emergency documentation verified' : 'Emergency documentation not identified';
        } else {
            foreach ($eduRegistrationKeywords as $keyword) {
                if (str_contains($normalizedOcrText, mb_strtolower($keyword))) {
                    $hasPrimaryProof = true;
                    break;
                }
            }
            $enrollmentProofTypes = ['certificate of enrollment', 'enrollment form', 'registration form', 'officially enrolled', 'student id', 'school year'];
            if (in_array(mb_strtolower($acceptedProofType ?? ''), $enrollmentProofTypes)) {
                $hasPrimaryProof = true;
            }

            foreach ($eduTuitionKeywords as $keyword) {
                if (str_contains($normalizedOcrText, mb_strtolower($keyword))) {
                    $hasSecondaryProof = true;
                    break;
                }
            }
            $tuitionProofTypes = ['statement of account', 'tuition fee breakdown', 'tuition fee statement', 'billing'];
            if (in_array(mb_strtolower($acceptedProofType ?? ''), $tuitionProofTypes)) {
                $hasSecondaryProof = true;
            }
            $docSummary = $hasPrimaryProof && $hasSecondaryProof
                ? 'Educational documents verified'
                : 'Educational documents not fully verified';
        }

        // 3. Applicant name matching
        $nameMatch = false;
        if (!empty($normalizedApplicant)) {
            $applicantNoSpaces = preg_replace('/\s+/', '', $normalizedApplicant);
            $nameMatch = (
                str_contains($normalizedOcrText, $normalizedApplicant) ||
                str_contains($normalizedOcrText, $applicantNoSpaces)
            );
        } else {
            $nameMatch = $isValidDocument;
        }

        // SCENARIO B: Matched Documents — document check is ADVISORY only.
        // Recommended_amount, risk_level and risk_score come solely from
        // LoanEvaluationService (rule-based). The scanner result is stored
        // as advisory fields, never used to shift the score.
        if ($hasPrimaryProof && $hasSecondaryProof) {
            $score = $eval['risk_score'];
            $riskLevel = $eval['risk_level'] ?? 'Medium';
            $riskFactors = $eval['reasons'] ?? [];
            $riskFactors[] = 'Document check: passed';
            $has_risk_flags = ($score < 50);

            $note = 'Document check: passed — ' . $docSummary . ' for '
                . ($applicantName ?: 'the applicant') . '. Advisory only; '
                . 'recommended amount and risk level are rule-based and unchanged.';

            $this->persistScore($application, $loanApp, $score, $note, $riskLevel, $riskFactors, 'passed');
            return [
                'score'           => $score,
                'analysis_note'   => $note,
                'document_check'  => 'passed',
                'has_risk_flags'  => $has_risk_flags,
                'risk_level'      => $riskLevel,
                'risk_factors'    => $riskFactors,
                'recommended_amount' => $eval['recommended_amount'] ?? 0.0
            ];
        }

        // SCENARIO C: Mismatched / Invalid Documents — documents unverified, truthful note
        $score = $eval['risk_score'];
        if ($loanType === 'emergency') {
            $note = 'AI Scan Alert: Document mismatch detected. Uploaded document does not satisfy Emergency Loan Proof of Purpose requirements (Missing hospitalization, medical certificate, or calamity documentation). Documents not verified automatically; manual review required. Rule-based score computed from declared data only.';
        } else {
            $note = 'AI Scan Alert: Document mismatch detected. Uploaded document does not satisfy Proof of Purpose requirements (Missing Certificate of Enrollment or Statement of Account/Tuition Fee). Documents not verified automatically; manual review required. Rule-based score computed from declared data only.';
        }
        $riskLevel = $eval['risk_level'] ?? 'Medium';
        $riskFactors = $eval['reasons'] ?? [];
        $riskFactors[] = 'Document check: needs review';
        $has_risk_flags = true;

        $this->persistScore($application, $loanApp, $score, $note, $riskLevel, $riskFactors);

        return [
            'score'            => $score,
            'analysis_note'    => $note,
            'document_check'   => 'needs_review',
            'has_risk_flags'   => true,
            'risk_level'       => $riskLevel,
            'risk_factors'     => $riskFactors,
            'recommended_amount' => $eval['recommended_amount'] ?? 0.0
        ];
    }

    /**
     * Safely persist AI scan results to both Application and LoanApplication records.
     */
    protected function persistScore(?Application $application, $loanApp, int $score, string $note, string $riskLevel, array $riskFactors, string $documentCheck = 'unavailable'): void
    {
        if ($application) {
            $application->ai_score = $score;
            $application->ai_analysis_note = $note;
            $application->risk_level = $riskLevel;
            $application->risk_score = $score;
            $application->risk_factors = json_encode($riskFactors);
            $application->document_check = $documentCheck;
            if (Schema::hasColumn('applications', 'has_risk_flags')) {
                $application->has_risk_flags = ($score < 50) || ($documentCheck === 'needs_review');
            }
            $application->save();
        }

        if ($loanApp && $loanApp instanceof LoanApplication) {
            $loanApp->ai_score = $score;
            $loanApp->ai_analysis_notes = $note;
            $loanApp->document_check = $documentCheck;
            $loanApp->document_processing_status = match ($documentCheck) {
                'passed' => 'completed',
                'needs_review' => 'flagged',
                'manual_review', 'unavailable' => 'pending',
                default => 'pending',
            };
            $loanApp->risk_level = $riskLevel;
            $loanApp->risk_score = $score;
            $loanApp->risk_factors = json_encode($riskFactors);
            $loanApp->save();
        }
    }

    /**
     * Compute the base rule-based score and return full evaluation result
     * from LoanEvaluationService including reasons and recommended_amount.
     */
    private function computeBaseScoreWithEvaluation($loanApp): array
    {
        try {
            $eval = app(LoanEvaluationService::class)->evaluate($loanApp);
            return [
                'risk_score' => (int) $eval['risk_score'],
                'reasons'    => $eval['reasons'] ?? [],
                'recommended_amount' => $eval['recommended_amount'] ?? 0.0,
            ];
        } catch (\Throwable $e) {
            // Fallback if evaluation fails
            Log::warning('Failed to compute base score from LoanEvaluationService', [
                'loan_app_id' => $loanApp?->id,
                'error'       => $e->getMessage(),
            ]);
            return [
                'risk_score' => 50,
                'reasons'    => ['Rule-based evaluation unavailable'],
                'recommended_amount' => 0.0,
            ];
        }
    }

    /**
     * Calculate credit capacity score (0-100) from declared financial data only.
     * Document extraction is advisory; it never shifts the score.
     */
    public function evaluateCapacity(float $requestedAmount, float $declaredIncome): array
    {
        $reasons = [];
        $rawScore = 600;

        // Declared-income sanity vs requested amount
        if ($declaredIncome > 0) {
            $loanToIncomeRatio = $requestedAmount / $declaredIncome;
            if ($loanToIncomeRatio <= 3.0) {
                $rawScore += 120;
                $reasons[] = 'Low debt ratio relative to declared monthly income.';
            } elseif ($loanToIncomeRatio <= 6.0) {
                $rawScore += 80;
                $reasons[] = 'Moderate debt ratio relative to declared monthly income.';
            } else {
                $rawScore -= 100;
                $reasons[] = 'Requested loan exceeds safe monthly income capacity.';
            }
        }

        $finalRawScore = max(300, min(850, $rawScore));
        $score = round((($finalRawScore - 300) * 100) / 550);
        $status = $score >= 51 ? 'Approved' : 'Rejected';

        return [
            'score'   => $score,
            'status'  => $status,
            'summary' => implode('; ', $reasons),
        ];
    }
}