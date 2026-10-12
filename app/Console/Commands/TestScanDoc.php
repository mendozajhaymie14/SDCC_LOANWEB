<?php

namespace App\Console\Commands;

use App\Services\DocumentScannerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestScanDoc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:scan-doc {--file= : Path to the document file (local path or storage path) to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AI document scanner against an educational loan document.';

    /**
     * Accepted proof-of-purpose document types for educational loans.
     * Per SDCC loan matrix educational requirements.
     */
    protected const ACCEPTED_PROOF_TYPES = [
        'school registration form',
        'school report card',
        'statement of account',
        'tuition fee breakdown',
        'tuition fee statement',
        'list of books',
        'school supplies list',
        'official list of books',
    ];

    /**
     * Score interpretation for 0-100 scale testing.
     * Per the new AI credit scoring scale: 51-100 = Good (Green), 0-50 = Low Confidence (Red).
     */
    protected const SCORE_THRESHOLD = 51;

    /**
     * Educational expense markers the scanner must look for.
     * Per SDCC loan matrix educational requirements.
     */
    protected const EDUCATION_MARKERS = [
        'tuition', 'tuition fee', 'fees', 'registration', 'report card',
        'uniform', 'shoes', 'books', 'school supplies', 'school materials',
        'materials', 'textbook', 'textbooks', 'enrollment',
        'statement of account', 'balance', 'debits', 'credits',
        'school', 'university', 'college', 'academy', 'institute',
    ];

    /**
     * Execute the console command.
     */
    public function handle(DocumentScannerService $scanner): int
    {
        $filePath = $this->option('file');

        if (empty($filePath)) {
            $this->warn('No file provided. Please use --file=path/to/file.');
            $this->newLine();
            $this->runScenarioTests($scanner);
            return self::SUCCESS;
        }

        $fullPath = $this->resolveFilePath($filePath);

        if (!file_exists($fullPath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Scanning document: {$fullPath}");
        $this->newLine();

        $result = $scanner->analyzeDocument($fullPath);

        $this->printScanResult($result);
        $this->printEducationCheck($result);

        return self::SUCCESS;
    }

    /**
     * Resolve a file path from the --file option.
     */
    protected function resolveFilePath(string $filePath): string
    {
        // If it's a storage disk path, resolve to the full path.
        if (str_contains($filePath, '/')) {
            $parts = explode('/', $filePath, 2);
            $disk = $parts[0];
            $path = $parts[1];

            if (in_array($disk, ['public', 's3', 'azure', 'gcs'], true) && Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        // Otherwise treat as a local filesystem path.
        if (file_exists($filePath)) {
            return $filePath;
        }

        // Fallback: try public storage disk relative path.
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->path($filePath);
        }

        return $filePath;
    }

    /**
     * Run a set of in-memory scenario tests when no file is provided.
     * Demonstrates the scanner's validation rules without an LLM call.
     */
    protected function runScenarioTests(DocumentScannerService $scanner): int
    {
        $this->info('No --file provided. Running in-memory scenario checks.');
        $this->newLine();
        $this->info('Accepted proof-of-purpose types:');
        foreach (self::ACCEPTED_PROOF_TYPES as $type) {
            $this->line("  • {$type}");
        }
        $this->newLine();
        $this->info('Educational expense markers validated against scanned text:');
        foreach (self::EDUCATION_MARKERS as $marker) {
            $this->line("  • {$marker}");
        }
        $this->newLine();
        $this->info('Usage:');
        $this->line('  php artisan test:scan-doc --file=path/to/document.jpg');
        $this->line('  php artisan test:scan-doc --file=public/loan-documents/sample.pdf');

        return self::SUCCESS;
    }

    /**
     * Print the raw scanner result in a readable format.
     */
    protected function printScanResult(array $result): void
    {
        $this->newLine();
        $this->newLine();
$this->info('AI Extraction Result');

        $fields = [
            'document_type' => $result['document_type'] ?? 'N/A',
            'extracted_name' => $result['extracted_name'] ?? 'N/A',
            'monthly_gross_income' => $result['monthly_gross_income'] ?? 'N/A',
            'monthly_net_income' => $result['monthly_net_income'] ?? 'N/A',
            'match_confidence' => $result['match_confidence'] ?? 'N/A',
            'education_purpose' => $result['education_purpose'] ?? 'N/A',
            'accepted_proof_type' => $result['accepted_proof_type'] ?? 'N/A',
            'education_markers_found' => $result['education_markers_found'] ?? 'N/A',
            'is_legible' => $result['is_legible'] ?? 'N/A',
        ];

        foreach ($fields as $label => $value) {
            $value = is_bool($value) ? ($value ? 'YES' : 'NO') : $value;
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            $this->line("  {$label}: {$value}");
        }

        if (!empty($result['education_markers_details'])) {
            $this->newLine();
            $this->line('  Details:');
            foreach ($result['education_markers_details'] as $detail) {
                $this->line("    • {$detail}");
            }
        }

        if (!empty($result['fraud_flags'])) {
            $this->newLine();
            $this->error('  Fraud/Validation Flags:');
            foreach ($result['fraud_flags'] as $flag) {
                $this->error("    • {$flag}");
            }
        }
    }

    /**
     * Print the educational-purpose validation check.
     */
    protected function printEducationCheck(array $result): void
    {
        $this->newLine();
        $this->info('Educational Loan Validation');

        $isLegible = $result['is_legible'] ?? false;
        $acceptedProof = in_array(
            strtolower($result['accepted_proof_type'] ?? ''),
            array_map('strtolower', self::ACCEPTED_PROOF_TYPES),
            true
        );
        $markers = $result['education_markers_found'] ?? [];
        $markerCount = is_array($markers) ? count($markers) : 0;

        $checks = [];

        if ($isLegible) {
            $checks[] = 'PASS — Document is legible.';
        } else {
            $checks[] = 'FAIL — Document is unreadable/poor quality.';
        }

        if ($acceptedProof) {
            $checks[] = 'PASS — Accepted proof-of-purpose document type.';
        } else {
            $checks[] = 'FAIL — Document is not one of the accepted proofs of purpose.';
        }

        if ($markerCount > 0) {
            $checks[] = "PASS — {$markerCount} educational expense marker(s) found.";
        } else {
            $checks[] = 'FAIL — No educational expense markers detected.';
        }

        foreach ($checks as $check) {
            $this->line('  ' . $check);
        }

        $allPass = $isLegible && $acceptedProof && $markerCount > 0;
        $this->newLine();
        if ($allPass) {
            $this->info('  OVERALL RESULT: Document PASSES the educational loan requirements.');
        } else {
            $this->error('  OVERALL RESULT: Document FAILS the educational loan requirements. See failed checks above.');
        }
    }
}
