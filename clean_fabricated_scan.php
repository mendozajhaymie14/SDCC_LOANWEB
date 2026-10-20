<?php
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$affected = 0;
$rows = DB::select("SELECT id, app_id, ai_analysis_notes FROM loan_applications WHERE (ai_analysis_notes LIKE '%verified%') AND (last_scanned_at IS NULL)");
echo "Rows to clear (note contains 'verified' and last_scanned_at is NULL): ", count($rows), "\n";
foreach($rows as $r){
    echo "  id={$r->id} app_id={$r->app_id}: ", substr($r->ai_analysis_notes ?? '', 0, 60), "...\n";
}

$now = now();
DB::table('loan_applications')
    ->where(function($q){
        $q->where('ai_analysis_notes', 'LIKE', '%verified%')->whereNull('last_scanned_at');
    })
    ->update([
        'ai_analysis_notes' => null,
        'extracted_document_data' => null,
        'document_processing_status' => 'pending',
        'last_scanned_at' => $now,
    ]);

$affected = DB::table('loan_applications')->where(function($q){
    $q->where('ai_analysis_notes', 'LIKE', '%verified%')->whereNull('last_scanned_at');
})->count();
echo "\nCleared ";
echo $affected;
echo " row(s)\n";

// Also clear fabricated note in applications table (mirror rows that reference scan results)
$appsAffected = DB::table('applications')
    ->where(function($q){
        $q->where('ai_analysis_note', 'LIKE', '%verified%')->whereNull('last_scanned_at');
    })
    ->update([
        'ai_analysis_note' => null,
        'document_processing_status' => 'pending',
        'last_scanned_at' => $now,
    ]);
echo "Cleared ";
echo $appsAffected;
echo " application(s)\n";

// Verify remaining rows with NULL scans
echo "\n=== After cleanup ===\n";
$remaining = DB::select("SELECT id, app_id, ai_analysis_notes, document_processing_status, last_scanned_at FROM loan_applications WHERE ai_analysis_notes IS NOT NULL OR document_processing_status != 'pending'");
echo "Remaining rows with scanned notes: ", count($remaining), "\n";
foreach($remaining as $r){
    echo "  id={$r->id} app_id={$r->app_id} status={$r->document_processing_status} last_scanned={$r->last_scanned_at}\n";
}
?>