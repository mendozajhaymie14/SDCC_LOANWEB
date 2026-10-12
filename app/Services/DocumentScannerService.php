<?php

namespace App\Services;

use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class DocumentScannerService
{
    /**
     * Sends a document (ID card, payslip, or proof of billing) to Gemini
     * and returns structured JSON extraction.
     *
     * States:
     *   State 1: No documents attached → early return with critical flag
     *   State 2: Valid document (Gemini: is_valid_document=true) → score 85-95
     *   State 3: Invalid/wrong document (Gemini: is_valid_document=false) → score 25
     */
    public function analyzeDocument(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'is_legible' => false,
                'fraud_flags' => ['File not found'],
                'state' => 1,
            ];
        }

        $mimeTypeStr = mime_content_type($filePath);
        $fileContent = file_get_contents($filePath);

        $mimeType = match ($mimeTypeStr) {
            'image/png'  => MimeType::IMAGE_PNG,
            'image/jpeg' => MimeType::IMAGE_JPEG,
            'image/jpg'  => MimeType::IMAGE_JPEG,
            'application/pdf' => MimeType::IMAGE_JPEG, // Gemini handles PDF via inlineData; map to JPEG as fallback
            default => MimeType::IMAGE_JPEG,
        };

        $prompt = <<<'PROMPT'
You are an automated loan document auditor for a credit cooperative. This document supports a PROVIDENT - EDUCATIONAL LOAN application.

The loan purpose is strictly: payment of tuition fees, purchase of uniforms, shoes, books, and other school materials.

The ONLY accepted proofs of purpose are:
1. School registration form or report card
2. Statement of Account / Tuition fee breakdown
3. Official list of books / school supplies

Examine this document and return ONLY a valid JSON object with this exact structure — no markdown, no explanation:

{
  "document_type": "payslip" | "billing" | "id_card" | "unknown",
  "extracted_name": "Full Name",
  "monthly_gross_income": 0.00,
  "monthly_net_income": 0.00,
  "is_legible": true,
  "match_confidence": 0.95,
  "fraud_flags": [],
  "accepted_proof_type": "one of: school registration form, school report card, statement of account, tuition fee breakdown, tuition fee statement, list of books, school supplies list, official list of books, or null if not applicable",
  "education_markers_found": ["array of educational expense markers found in document, such as: tuition, tuition fee, fees, registration, report card, uniform, shoes, books, school supplies, school materials, materials, textbook, textbooks, enrollment, statement of account, balance, debits, credits, school, university, college, academy, institute"],
  "education_purpose": "boolean indicating if document supports educational purpose (true/false)",
  "is_valid_document": true/false
}

If the document is unreadable, set is_legible to false and fraud_flags to ["Unreadable document"].
If a field cannot be determined, use null or 0.00 or empty array as appropriate.
PROMPT;

        try {
            $blob = new Blob(
                mimeType: $mimeType,
                data: base64_encode($fileContent),
            );

            $response = Gemini::generativeModel('gemini-3.1-flash-lite-preview')->generateContent(
                $prompt,
                $blob
            );

            $rawText = $response->text();

            // Strip markdown code fences if present
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($rawText));

            $decoded = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'is_legible'      => false,
                    'fraud_flags'     => ['Failed to parse JSON response: ' . json_last_error_msg()],
                    'raw_text'        => '',
                    'state'           => 1,
                ];
            }

            // Determine document validity from Gemini's response
            $isValidDocument = !empty($decoded['is_valid_document']) && $decoded['is_valid_document'] === true;

            // Preserve the raw OCR text so downstream services can perform verification
            return [
                'is_legible'      => $decoded['is_legible'] ?? false,
                'fraud_flags'     => $decoded['fraud_flags'] ?? [],
                'raw_text'        => trim($cleanJson),
                'document_type'   => $decoded['document_type'] ?? 'unknown',
                'accepted_proof_type' => $decoded['accepted_proof_type'] ?? null,
                'education_markers_found' => $decoded['education_markers_found'] ?? [],
                'education_purpose' => !empty($decoded['education_purpose']) && $decoded['education_purpose'] === true,
                'is_valid_document' => $isValidDocument,
                'state'           => $isValidDocument ? 2 : 3,
            ] + $decoded;
        } catch (\Throwable $e) {
            Log::error('Gemini document scan failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_legible' => false,
                'fraud_flags' => ['AI service unavailable'],
                'state' => 1,
            ];
        }
    }
}