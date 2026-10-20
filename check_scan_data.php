<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CURRENT SCAN DATA (loan_applications) ===\n";
$rows = DB::select("SELECT id, app_id, ai_analysis_notes, extracted_document_data, document_processing_status, last_scanned_at, risk_score, risk_level, risk_factors FROM loan_applications");
foreach($rows as $r){
    echo "id={$r->id} app_id={$r->app_id}\n";
    echo "  ai_analysis_notes: ", substr($r->ai_analysis_notes ?? 'NULL', 0, 80), "\n";
    echo "  document_processing_status: ", $r->document_processing_status ?? 'NULL', "\n";
    echo "  last_scanned_at: ", $r->last_scanned_at ?? 'NULL', "\n";
    echo "  risk_score: ", $r->risk_score ?? 'NULL', " risk_level: ", $r->risk_level ?? 'NULL', "\n";
    echo "  risk_factors: ", substr($r->risk_factors ?? 'NULL', 0, 80), "\n";
    echo "\n";
}
?>