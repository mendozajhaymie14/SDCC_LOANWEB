<?php

namespace App\Services;

use App\Models\LoanApplication;

class CreditScoringService
{
    /**
     * Evaluate an application through the strict 3-state AI scanning pipeline.
     *
     * States:
     *   State 1 (No Documents): no files attached → ai_score = 20, critical flag
     *   State 2 (Valid Document): Gemini validates the educational proof → ai_score = 90 (85-95)
     *   State 3 (Invalid Document): Gemini rejects the document → ai_score = 25
     *
     * The $documents array is checked first for State 1; if populated, Gemini
     * evaluates the images for educational loan proof. Returns ai_score and
     * ai_analysis_notes suitable for both LoanApplication and Application tables.
     */
    public function evaluateAndScan(array $documents, LoanApplication $loanApplication, ?string $applicantName): array
    {
        // State 1: No supporting documents attached.
        if (empty($documents)) {
            return [
                'score'          => 20,
                'notes'          => '⚠️ Critical Flag: No supporting documents uploaded. Proof of enrollment or tuition statement is required.',
                'state'          => 1,
                'status'         => 'rejected',
                'documentStatus' => 'no_documents',
            ];
        }

        // State 2/3: Send attached images to Gemini for educational loan proof.
        $scanner = app(\App\Services\DocumentScannerService::class);
        $geminiNotes = [];
        $geminiStates = [];
        $geminiScores = [];
        $geminiExtractions = [];
        $allValid = true;
        $geminiNote = '';

        foreach ($documents as $path) {
            $fullPath = storage_path('app/public/' . $path);
            if (!file_exists($fullPath)) {
                $allValid = false;
                continue;
            }

            $extraction = $scanner->analyzeDocument($fullPath);
            $geminiExtractions[] = $extraction;
            $geminiStates[] = $extraction['state'] ?? 1;

            // Extract a human-readable note from Gemini's response.
            $note = $extraction['gemini_note'] ?? $this->extractGeminiNote($extraction);
            $geminiNotes[] = $note;

            // Track scores suggested by Gemini (or derived from validity).
            $score = $extraction['gemini_score'] ?? null;
            if ($score === null) {
                $score = ($extraction['is_valid_document'] ?? false) ? rand(85, 95) : 25;
            }
            $geminiScores[] = $score;
        }

        // Determine the dominant outcome across all uploaded documents.
        $validCount = 0;
        $invalidCount = 0;
        $state1Count = 0;
        foreach ($geminiStates as $state) {
            if ($state === 2) {
                $validCount++;
            } elseif ($state === 3) {
                $invalidCount++;
            } else {
                $state1Count++;
            }
        }

        // Priority: any invalid → State 3. Otherwise any valid → State 2.
        if ($invalidCount > 0) {
            // STATE 3: Invalid / wrong document uploaded.
            $score = 25;
            $geminiNote = $invalidCount > 1
                ? implode('; ', $geminiNotes)
                : ($geminiNotes[0] ?? 'Uploaded files do not contain required proof of purpose or educational tuition details.');
            $notes = '⚠️ Invalid Document: ' . $geminiNote;

            return [
                'score'          => $score,
                'notes'          => $notes,
                'state'          => 3,
                'status'         => 'rejected',
                'documentStatus' => 'invalid_documents',
                'extractions'    => $geminiExtractions,
            ];
        }

        if ($validCount > 0) {
            // STATE 2: Valid educational document detected.
            $bestScore = max($geminiScores);
            $score = min(95, max(85, $bestScore)); // Clamp to 85-95, default 90.
            $geminiNote = $validCount > 1
                ? implode('; ', $geminiNotes)
                : ($geminiNotes[0] ?? 'Supporting documents match the educational loan purpose.');
            $notes = '✅ Verification Passed: ' . $geminiNote;

            return [
                'score'          => $score,
                'notes'          => $notes,
                'state'          => 2,
                'status'         => 'approved',
                'documentStatus' => 'valid_documents',
                'extractions'    => $geminiExtractions,
            ];
        }

        // No usable extraction was produced (State 1 files everywhere).
        return [
            'score'          => 20,
            'notes'          => '⚠️ Critical Flag: No supporting documents uploaded. Proof of enrollment or tuition statement is required.',
            'state'          => 1,
            'status'         => 'rejected',
            'documentStatus' => 'no_documents',
        ];
    }

    /**
     * Fall back to strict keyword verification (used when Gemini cannot be
     * reached or the extraction lacks explicit validity fields).
     *
     * OCR-extracted text MUST contain at least TWO required keywords
     * for the document to pass as educational/tuition proof.
     */
    public function evaluateCapacity(float $requestedAmount, float $declaredIncome, array $extraction, ?string $applicantName = null): array
    {
        $rawText = strtolower($extraction['raw_text'] ?? '');
        $requiredKeywords = [
            'tuition', 'statement of account', 'certificate of enrollment',
            'registration', 'student', 'school', 'university', 'billing',
            'report card', 'enrollment', 'uniform', 'books', 'school supplies',
            'tuition fee', 'school materials', 'textbook',
        ];
        $matchedCount = 0;
        foreach ($requiredKeywords as $keyword) {
            if (str_contains($rawText, $keyword)) {
                $matchedCount++;
            }
        }
        $hasEducationalKeywords = $matchedCount >= 2;

        if (!$hasEducationalKeywords) {
            $rawScore = rand(150, 300); // Maps to ~15-30 on 0-100 scale
            $reasons = ['⚠️ Invalid Document: Uploaded files do not contain required proof of purpose or educational tuition details.'];
        } else {
            $rawScore = 700;
            $reasons = ['Educational document verified — tuition requirements met.'];
        }

        $finalRawScore = max(300, min(850, $rawScore));
        $score = round((($finalRawScore - 300) * 100) / 550);

        return [
            'score'  => $score,
            'status' => $score >= 51 ? 'Approved' : 'Rejected',
            'summary' => implode('; ', $reasons),
        ];
    }

    /**
     * Try to extract a human-readable note from a Gemini extraction.
     */
    private function extractGeminiNote(array $extraction): string
    {
        $fraudFlags = $extraction['fraud_flags'] ?? [];
        $docType = $extraction['document_type'] ?? 'unknown';
        $legible = $extraction['is_legible'] ?? false;

        if (!$legible) {
            return 'Document is unreadable or poor quality.';
        }

        if (!empty($fraudFlags)) {
            return implode('; ', $fraudFlags);
        }

        if ($extraction['education_purpose'] ?? false) {
            return 'Document supports educational purpose with relevant markers.';
        }

        if ($docType === 'unknown') {
            return 'Document type could not be determined.';
        }

        return "Document type: {$docType}.";
    }

    /**
     * Generate a human-readable scan analysis note (verification badge)
     * based on the extracted data and application documents.
     */
    public function generateScanNote(array $extraction, array $documents, ?string $applicantName): string
    {
        // a. No files attached → Critical flag
        if (empty($documents)) {
            return '⚠️ Critical Flag: No supporting documents uploaded. Baseline profile evaluation applied.';
        }

        // b. Check for name match in document contents
        if ($applicantName && !empty($documents)) {
            $normalizedApplicantName = preg_replace('/\s+/', ' ', mb_strtolower(trim($applicantName)));
            $applicantParts = preg_split('/\s+/', trim($applicantName));
            $partialName = '';
            if (\count($applicantParts) >= 2) {
                $partialName = mb_strtolower($applicantParts[0] . ' ' . end($applicantParts));
            }

            $extractedText = strtolower($extraction['raw_text'] ?? '');
            $nameMatch = false;

            if (mb_strtolower($normalizedApplicantName) !== '' &&
                (str_contains($extractedText, $normalizedApplicantName) ||
                 ($partialName !== '' && str_contains($extractedText, $partialName)))) {
                $nameMatch = true;
            }

            if (!$nameMatch) {
                return '⚡ Integrity Warning: Document name does not match applicant profile name.';
            }
        }

        // c. Documents verified successfully
        if (!empty($extraction) && !empty($documents)) {
            return '✅ Verification Passed: Supporting documents match applicant profile and tuition requirements.';
        }

        // Default fallback
        return 'ℹ️ Scan in progress — analysis pending.';
    }

    /**
     * Check if document content matches educational keywords and name.
     * Requires AT LEAST TWO educational keywords in the extracted text.
     */
    public function hasEducationalDocumentMatch(array $extraction, ?string $applicantName): bool
    {
        if (empty($extraction)) {
            return false;
        }

        $rawText = strtolower($extraction['raw_text'] ?? '');
        $requiredKeywords = [
            'tuition', 'statement of account', 'certificate of enrollment',
            'registration', 'student', 'school', 'university', 'billing',
            'report card', 'enrollment', 'uniform', 'books', 'school supplies',
            'tuition fee', 'school materials', 'textbook',
        ];
        $matchedCount = 0;
        foreach ($requiredKeywords as $keyword) {
            if (str_contains($rawText, $keyword)) {
                $matchedCount++;
            }
        }

        $hasEduKeyword = $matchedCount >= 2;

        $nameMatch = false;
        if ($applicantName) {
            $normalizedApplicantName = preg_replace('/\s+/', ' ', mb_strtolower(trim($applicantName)));
            $applicantParts = preg_split('/\s+/', trim($applicantName));
            $partialName = '';
            if (\count($applicantParts) >= 2) {
                $partialName = mb_strtolower($applicantParts[0] . ' ' . end($applicantParts));
            }

            if (mb_strtolower($normalizedApplicantName) !== '' &&
                (str_contains($rawText, $normalizedApplicantName) ||
                 ($partialName !== '' && str_contains($rawText, $partialName)))) {
                $nameMatch = true;
            }
        }

        return $hasEduKeyword && $nameMatch;
    }
}