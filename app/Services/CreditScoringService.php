<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Facades\Log;

class CreditScoringService
{
    /**
     * Performs 3-tier AI document analysis scan on an Application instance.
     * Evaluates uploaded documents against Educational Loan Proof of Purpose requirements
     * and returns a credit score with detailed notes.
     *
     * Three Scenarios:
     *   Scenario A: No uploaded documents          → ai_score = 30, warning note
     *   Scenario B: Matched Educational Documents   → ai_score = 85, approval note
     *   Scenario C: Mismatched / Invalid Documents   → ai_score = 40, mismatch note
     *
     * @return array{'ai_score': int, 'ai_analysis_note': string, 'status': string}
     */
    public function evaluateAndScan($application, ?array $documents = null): array
    {
        // Accept documents from parameter or try to get from LoanApplication relationship
        if ($documents === null) {
            $loanApp = $application->loanApplication;
            $documents = $loanApp->documents ?? [];
        }
        $documents = $documents ?? [];

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

        // Get applicant name for matching (borrower_name or applicant full name)
        $applicantName = trim($application->borrower_name ?? $application->applicant ?? '');

        // Scenario A: No uploaded documents
        if (empty($documents)) {
            $score = 30;
            $note = 'AI Scan Warning: No supporting documents uploaded. Missing School Registration/ID, Statement of Account/Tuition Fee, or School Materials List.';
            $application->ai_score = $score;
            $application->ai_analysis_note = $note;
            $application->save();
            return [
                'ai_score' => 30,
                'ai_analysis_note' => $note,
                'status' => 'warning'
            ];
        }

        // Use DocumentScannerService to analyze the first document
        $scanner = new DocumentScannerService();
        $firstDoc = $documents[0] ?? null;

        if (!$firstDoc) {
            // Edge case: documents array exists but is empty or has no file paths
            $score = 30;
            $note = 'AI Scan Warning: No supporting documents uploaded. Missing School Registration/ID, Statement of Account/Tuition Fee, or School Materials List.';
            $application->ai_score = $score;
            $application->ai_analysis_note = $note;
            $application->save();
            return [
                'ai_score' => 30,
                'ai_analysis_note' => $note,
                'status' => 'warning'
            ];
        }

        // Build the full absolute disk path for the document.
        // Gemini Vision API requires the absolute path to read the file.
        $filePath = $firstDoc;

        // If the given path is not absolute or does not exist, resolve it.
        if (!file_exists($filePath)) {
            // Option 1: storage_path('app/public/' . $relativePath)
            $storagePath = storage_path('app/public/' . $filePath);
            if (file_exists($storagePath)) {
                $filePath = $storagePath;
            } else {
                // Option 2: public_path('storage/' . $relativePath)
                $publicPath = public_path('storage/' . $filePath);
                if (file_exists($publicPath)) {
                    $filePath = $publicPath;
                }
            }
        }

        // Analyze the document using DocumentScannerService (Gemini Vision / OCR)
        $scanResult = $scanner->analyzeDocument($filePath ?? '');

        // Extract OCR text and validation data from the Gemini scan result
        $ocrText = $scanResult['raw_text'] ?? '';
        $educationPurpose = $scanResult['education_purpose'] ?? false;
        $educationMarkers = $scanResult['education_markers_found'] ?? [];
        $acceptedProofType = $scanResult['accepted_proof_type'] ?? null;
        $isValidDocument = $scanResult['is_valid_document'] ?? false;

        // Normalize text for case-insensitive matching (e.g., "JUAN DELA CRUZ" handling)
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
        // Also accept Gemini-recognized enrollment proof types
        $enrollmentProofTypes = ['certificate of enrollment', 'enrollment form', 'registration form',
                                'officially enrolled', 'student id', 'school year'];
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
        // Also accept Gemini-recognized tuition proof types
        $tuitionProofTypes = ['statement of account', 'tuition fee breakdown', 'tuition fee statement', 'billing'];
        if (in_array(mb_strtolower($acceptedProofType ?? ''), $tuitionProofTypes)) {
            $hasTuitionProof = true;
        }

        // 3. Applicant name matching: e.g. "JUAN DELA CRUZ" must match borrower_name/applicant
        $nameMatch = false;
        if (!empty($normalizedApplicant)) {
            $applicantNoSpaces = preg_replace('/\s+/', '', $normalizedApplicant);
            $nameMatch = (
                str_contains($normalizedOcrText, $normalizedApplicant) ||   // exact name match
                str_contains($normalizedOcrText, $applicantNoSpaces)         // name without spaces
            );
        } else {
            // No applicant name available; rely on Gemini's document validation
            $nameMatch = $isValidDocument;
        }

        // SCENARIO B: Matched Educational Documents (Passes Proof of Purpose)
        // Condition: BOTH Certificate of Enrollment AND Statement of Account / Tuition Fee
        // detected, with applicant name match
        if ($hasEnrollmentProof && $hasTuitionProof && $nameMatch) {
            $score = 85;
            $note = 'AI Scan Complete: Verified Educational Loan requirements. Certificate of Enrollment and Statement of Account for ' .
                    ($applicantName ?: 'the applicant') .
                    ' matched successfully.';
            $application->ai_score = $score;
            $application->ai_analysis_note = $note;
            $application->save();
            return [
                'ai_score' => 85,
                'ai_analysis_note' => $note,
                'status' => 'approved'
            ];
        }

        // SCENARIO C: Mismatched / Invalid Documents (Fails Proof of Purpose)
        // Documents uploaded but the required combination is not satisfied
        $score = 40;
        $note = 'AI Scan Alert: Document mismatch detected. The uploaded document does not satisfy Proof of Purpose requirements for Educational Loans (Missing School Registration or Tuition Statement).';
        $application->ai_score = $score;
        $application->ai_analysis_note = $note;
        $application->save();
        return [
            'ai_score' => 40,
            'ai_analysis_note' => $note,
            'status' => 'mismatch'
        ];
    }

    /**
     * Calculates credit score (Range: 0 - 100) based on AI extraction
     * and declared application data using cooperative financial rules.
     * Score 51-100: Passing / Good (Green in UI)
     * Score 0-50: Low Confidence / Flagged for Review (Red in UI)
     */
    public function evaluateCapacity(float $requestedAmount, float $declaredIncome, array $extraction): array
    {
        // Calculate raw score using original 300-850 scale
        $rawScore = 600; // Base baseline score
        $reasons = [];

        // 1. Check Document Legibility
        if (!($extraction['is_legible'] ?? true)) {
            $rawScore = 350;
            $reasons[] = 'Uploaded document was unreadable or poor quality.';
        } else {
            // 2. Income Verification Comparison
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

            // 3. Debt Service Ratio (Loan Amount vs Monthly Net Income)
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

            // 4. Fraud Flags Penalty
            if (!empty($extraction['fraud_flags'])) {
                $rawScore -= 150;
                $reasons[] = 'Risk flags detected on submitted document.';
            }
        }

        // Clamp final score range between 300 and 850 (original scale)
        $finalRawScore = max(300, min(850, $rawScore));

        // Convert to 0-100 scale: map 300-850 range to 0-100
        // Formula: new_score = ((old_score - 300) * 100) / (850 - 300)
        // This maps: 300→0, 500→36.36, 700→72.73, 850→100
        $score = (($finalRawScore - 300) * 100) / 550;

        // Round to nearest integer
        $score = round($score);

        // Determine Loan Status based on new 0-100 scale
        // Score 51-100: Passing / Good
        // Score 0-50: Low Confidence / Flagged for Review
        $status = $score >= 51 ? 'Approved' : 'Rejected';

        return [
            'score' => $score,

            'status' => $status,
            'summary' => implode('; ', $reasons),
        ];
    }
}