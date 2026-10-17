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
     * Three Scenarios:
     *   Scenario A: No uploaded documents          → score = 30, warning note
     *   Scenario B: Matched Educational Documents   → score = 85 (or 25 on name mismatch)
     *   Scenario C: Mismatched / Invalid Documents   → score = 40, mismatch note
     *
     * @param LoanApplication|Application $loanApp
     * @param array|null $documents
     * @return array{'score': int, 'analysis_note': string, 'has_risk_flags': bool}
     */
    public function evaluateAndScan($loanApp, ?array $documents = null): array
    {
        // Accept documents from parameter or model attributes
        $documents = $documents ?? (is_array($loanApp->documents ?? null) ? $loanApp->documents : []);

        // Resolve applicant name and linked application safely
        $applicantName = trim($loanApp->full_name ?? $loanApp->applicant ?? '');
        $application = ($loanApp instanceof Application) ? $loanApp : ($loanApp->application ?? null);

        // Expanded Educational Loan keyword verification rules
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

        // Scenario A: No uploaded documents
        if (empty($documents)) {
            $score = 30;
            $note = 'AI Scan Warning: No supporting documents uploaded. Missing School Registration/ID, Statement of Account/Tuition Fee, or School Materials List.';
            $this->persistScore($application, $loanApp, $score, $note);
            return [
                'score' => 30,
                'analysis_note' => $note,
                'has_risk_flags' => true
            ];
        }

        $scanner = new DocumentScannerService();
        $firstDoc = $documents[0] ?? null;

        if (!$firstDoc) {
            $score = 30;
            $note = 'AI Scan Warning: No supporting documents uploaded. Missing School Registration/ID, Statement of Account/Tuition Fee, or School Materials List.';
            $this->persistScore($application, $loanApp, $score, $note);
            return [
                'score' => 30,
                'analysis_note' => $note,
                'has_risk_flags' => true
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
        $scanResult = $scanner->analyzeDocument($filePath ?? '');

        // Extract OCR text and validation data from the scan result
        $ocrText = $scanResult['raw_text'] ?? '';
        $acceptedProofType = $scanResult['accepted_proof_type'] ?? null;
        $isValidDocument = $scanResult['is_valid_document'] ?? false;

        // Normalize text for case-insensitive matching
        $normalizedOcrText = mb_strtolower($ocrText);
        $normalizedApplicant = mb_strtolower($applicantName);

        // 1. Check for Certificate of Enrollment / Registration proof
        $hasEnrollmentProof = false;
        foreach ($eduRegistrationKeywords as $keyword) {
            if (str_contains($normalizedOcrText, mb_strtolower($keyword))) {
                $hasEnrollmentProof = true;
                break;
            }
        }
        $enrollmentProofTypes = ['certificate of enrollment', 'enrollment form', 'registration form', 'officially enrolled', 'student id', 'school year'];
        if (in_array(mb_strtolower($acceptedProofType ?? ''), $enrollmentProofTypes)) {
            $hasEnrollmentProof = true;
        }

        // 2. Check for Statement of Account / Tuition Fee proof
        $hasTuitionProof = false;
        foreach ($eduTuitionKeywords as $keyword) {
            if (str_contains($normalizedOcrText, mb_strtolower($keyword))) {
                $hasTuitionProof = true;
                break;
            }
        }
        $tuitionProofTypes = ['statement of account', 'tuition fee breakdown', 'tuition fee statement', 'billing'];
        if (in_array(mb_strtolower($acceptedProofType ?? ''), $tuitionProofTypes)) {
            $hasTuitionProof = true;
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

        // SCENARIO B: Matched Educational Documents
        if ($hasEnrollmentProof && $hasTuitionProof) {
            if ($nameMatch) {
                $score = 85;
                $note = 'AI Scan Complete: Verified Educational Loan requirements for ' . ($applicantName ?: 'the applicant') . ' matched successfully.';
                $has_risk_flags = false;
            } else {
                $score = 25;
                $note = '❌ CRITICAL RISK: Document name mismatch. Uploaded document does not match applicant name (' . ucwords($applicantName) . ').';
                $has_risk_flags = true;
            }

            $this->persistScore($application, $loanApp, $score, $note);
            return [
                'score' => $score,
                'analysis_note' => $note,
                'has_risk_flags' => $has_risk_flags
            ];
        }

        // SCENARIO C: Mismatched / Invalid Documents
        $score = 40;
        $note = 'AI Scan Alert: Document mismatch detected. Uploaded document does not satisfy Proof of Purpose requirements (Missing School Registration or Tuition Statement).';
        $this->persistScore($application, $loanApp, $score, $note);

        return [
            'score' => $score,
            'analysis_note' => $note,
            'has_risk_flags' => true
        ];
    }

    /**
     * Safely persist AI scan results to both Application and LoanApplication records.
     */
    protected function persistScore(?Application $application, $loanApp, int $score, string $note): void
    {
        if ($application) {
            $application->ai_score = $score;
            $application->ai_analysis_note = $note;
            if (Schema::hasColumn('applications', 'has_risk_flags')) {
                $application->has_risk_flags = ($score < 50);
            }
            $application->save();
        }

        if ($loanApp && $loanApp instanceof LoanApplication) {
            $loanApp->ai_score = $score;
            $loanApp->ai_analysis_notes = $note;
            $loanApp->document_processing_status = ($score < 50) ? 'flagged' : 'completed';
            $loanApp->save();
        }
    }

    /**
     * Calculates credit score (Range: 0 - 100) based on AI extraction and declared financial data.
     */
    public function evaluateCapacity(float $requestedAmount, float $declaredIncome, array $extraction, int $documentScore = null): array
    {
        if ($documentScore !== null) {
            $rawScore = 300 + (($documentScore - 30) * (550 / 55));
        } else {
            $rawScore = 600;
        }

        if ($documentScore === 30) {
            $rawScore = 350;
        } elseif ($documentScore === 40) {
            $rawScore = 400;
        } elseif ($documentScore === 85) {
            $rawScore = 600;
        } elseif ($documentScore === 25) {
            $rawScore = 300;
        }
        $reasons = [];

        if (!($extraction['is_legible'] ?? true)) {
            $rawScore = 350;
            $reasons[] = 'Uploaded document was unreadable or poor quality.';
        } else {
            $verifiedIncome = $extraction['monthly_net_income'] ?? null;
            if ($verifiedIncome && $declaredIncome > 0) {
                $variance = abs($verifiedIncome - $declaredIncome) / $declaredIncome;
                if ($variance > 0.25) {
                    $rawScore -= 50;
                    $reasons[] = 'Declared income differs significantly from scanned document.';
                } else {
                    $rawScore += 50;
                    $reasons[] = 'Declared income verified by document scan.';
                }
            }

            if ($verifiedIncome && $verifiedIncome > 0) {
                $loanToIncomeRatio = $requestedAmount / $verifiedIncome;
                if ($loanToIncomeRatio <= 3.0) {
                    $rawScore += 120;
                    $reasons[] = 'Low debt ratio relative to monthly income.';
                } elseif ($loanToIncomeRatio <= 6.0) {
                    $rawScore += 80;
                    $reasons[] = 'Moderate debt ratio relative to monthly income.';
                } else {
                    $rawScore -= 100;
                    $reasons[] = 'Requested loan exceeds safe monthly income capacity.';
                }
            }

            if (!empty($extraction['fraud_flags'])) {
                $rawScore -= 150;
                $reasons[] = 'Risk flags detected on submitted document.';
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