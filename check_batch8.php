<?php
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== All migrations in batch 8 ===\n";
$migs = DB::table('migrations')->where('batch', 8)->get();
foreach($migs as $m){
    echo $m->migration, PHP_EOL;
}

echo "\n=== applications table columns (check_scanned) ===\n";
$cols = DB::select("DESCRIBE applications");
$found = false;
foreach($cols as $c){
    if ($c->Field === 'last_scanned_at' || $c->Field === 'admin_remarks') {
        echo "FOUND: ", $c->Field, " | ", $c->Type, " | null=", $c->Null, PHP_EOL;
        $found = true;
    }
}
if (!$found) {
    echo "Neither last_scanned_at nor admin_remarks present\n";
}
?>