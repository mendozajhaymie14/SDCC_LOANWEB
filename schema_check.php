<?php
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name='borrowers'");
foreach ($tables as $row) {
    echo $row->sql, "\n";
}
