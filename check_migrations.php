<?php
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$migs = DB::table('migrations')->where('migration', 'like', '%2026_10_19_041817%')->orWhere('migration', 'like', '%2026_10_19_042041%')->get();
foreach($migs as $m){
    echo $m->migration, ' | batch ', $m->batch, PHP_EOL;
}
?>