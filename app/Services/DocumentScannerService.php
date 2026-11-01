<?php

namespace App\Services;

use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class DocumentScannerService
{
    /**
     * Sends a document (ID card, payslip, or proof of billing) to Gemini
     * and returns structured JSON extraction.
     *
     * States:
     *   State 1: No documents attached or AI unavailable → early return with advisory flag
     *   State 2: Valid document (Gemini: is_valid_document=true) → document_check: passed
     *   State 3: Invalid/wrong document (Gemini: is_valid_document=false) → document_check: needs_review
     */
    public function analyzeDocument(string $filePath, string $loanType = 'educational'): array
    {
        if (!file_exists($filePath)) {
            return [
                'is_legible'      => false,
                'fraud_flags'     => ['File not found'],
                'document_check'  => 'unavailable',
                'state'           => 1,
            ];
        }

        $mimeTypeStr = mime_content_type($filePath);
        $fileContent = file_get_contents($filePath);

        // Only images are supported for inline processing; PDFs require manual review
        $supportedMimes = [
            'image/png'  => MimeType::IMAGE_PNG,
            'image/jpeg' => MimeType::IMAGE_JPEG,
            'image/jpg'  => MimeType::IMAGE_JPEG,
        ];

        if (!isset($supportedMimes[$mimeTypeStr])) {
            // PDF or other unsupported type — mark for manual review, don't block
            return [
                'is_legible'      => false,
                'fraud_flags'     => ['Unsupported document type: ' . $mimeTypeStr . ' — manual review required'],
                'document_check'  => 'manual_review',
                'state'           => 1,
                'mime_type'       => $mimeTypeStr,
            ];
        }

        $mimeType = $supportedMimes[$mimeTypeStr];

        // Load accepted proofs from config per loan type
        $acceptedProofs = Config::get('loan_matrix.required_proofs.' . $loanType, []);
        $proofList = implode(', ', $acceptedProofs);

        $prompt = "You are an automated loan document auditor for a credit cooperative. " .
            "This document supports a " . $loanType . " loan application.\n\n" .
            "The ONLY accepted proofs of purpose are:\n" .
            $proofList . "\n\n" .
            "Examine this document and return ONLY a valid JSON object with this exact structure — no markdown, no explanation:\n\n" .
            "{\n" .
            "  \"document_type\": \"payslip\" | \"billing\" | \"id_card\" | \"unknown\",\n" .
            "  \"extracted_name\": \"Full Name\",\n" .
            "  \"monthly_gross_income\": 0.00,\n" .
            "  \"monthly_net_income\": 0.00,\n" .
            "  \"is_legible\": true,\n" .
            "  \"match_confidence\": 0.95,\n" .
            "  \"fraud_flags\": [],\n" .
            "  \"accepted_proof_type\": \"one of the accepted proofs above, or null if not applicable\",\n" .
            "  \"education_markers_found\": [\"array of educational expense markers found in document\"],\n" .
            "  \"education_purpose\": \"boolean indicating if document supports educational purpose (true/false)\",\n" .
            "  \"is_valid_document\": true/false\n" .
            "}\n\n" .
            "If the document is unreadable, set is_legible to false and fraud_flags to [\"Unreadable document\"].\n" .
            "If a field cannot be determined, use null or 0.00 or empty array as appropriate.";

        try {
            $blob = new Blob(
                mimeType: $mimeType,
                data: base64_encode($fileContent),
            );

            $model = Config::get('gemini.model', 'gemini-2.0-flash-exp');

            $response = Gemini::generativeModel($model)->generateContent(
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
                    'document_check'  => 'unavailable',
                    'state'           => 1,
                ];
            }

            // Determine document validity from Gemini's response
            $isValidDocument = !empty($decoded['is_valid_document']) && $decoded['is_valid_document'] === true;

            // Preserve the OCR text so downstream services can perform verification
            return [
                'is_legible'        => $decoded['is_legible'] ?? false,
                'fraud_flags'       => $decoded['fraud_flags'] ?? [],
                'raw_text'          => trim($cleanJson),
                'document_type'     => $decoded['document_type'] ?? 'unknown',
                'accepted_proof_type' => $decoded['accepted_proof_type'] ?? null,
                'education_markers_found' => $decoded['education_markers_found'] ?? [],
                'education_purpose' => !empty($decoded['education_purpose']) && $decoded['education_purpose'] === true,
                'is_valid_document' => $isValidDocument,
                'document_check'    => $isValidDocument ? 'passed' : 'needs_review',
                'state'             => $isValidDocument ? 2 : 3,
            ] + $decoded;
        } catch (\Throwable $e) {
            // Privacy: log only app ID and error type, never raw document content
            Log::warning('Gemini document scan failed', [
                'loan_type' => $loanType,
                'error_type' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'is_legible'      => false,
                'fraud_flags'     => ['AI service unavailable'],
                'document_check'  => 'unavailable',
                'state'           => 1,
            ];
        }
    }
}