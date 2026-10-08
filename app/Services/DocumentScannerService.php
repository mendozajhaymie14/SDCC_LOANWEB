<?php

namespace App\Services;

use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;

class DocumentScannerService
{
    /**
     * Sends a document (ID card, payslip, or proof of billing) to Gemini
     * and returns structured JSON extraction.
     */
    public function analyzeDocument(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'is_legible' => false,
                'fraud_flags' => ['File not found'],
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
You are an automated loan document auditor for a credit cooperative.
Examine this document (payslip, ID card, or proof of billing) and return ONLY a valid JSON object with this exact structure — no markdown, no explanation:

{
  "document_type": "payslip" | "billing" | "id_card" | "unknown",
  "extracted_name": "Full Name",
  "monthly_gross_income": 0.00,
  "monthly_net_income": 0.00,
  "is_legible": true,
  "match_confidence": 0.95,
  "fraud_flags": []
}

If the document is unreadable, set is_legible to false and fraud_flags to ["Unreadable document"].
If a field cannot be determined, use null or 0.00.
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
                    'is_legible' => false,
                    'fraud_flags' => ['Failed to parse JSON response: ' . json_last_error_msg()],
                ];
            }

            return [
                'is_legible' => $decoded['is_legible'] ?? false,
                'fraud_flags' => $decoded['fraud_flags'] ?? [],
            ] + $decoded;
        } catch (\Throwable $e) {
            \Log::error('Gemini document scan failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_legible' => false,
                'fraud_flags' => ['AI service unavailable'],
            ];
        }
    }
}