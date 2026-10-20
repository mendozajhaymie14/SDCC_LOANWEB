<?php
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = DB::select('DESCRIBE loan_applications');
foreach($cols as $c){
    echo $c->Field, ' | ', $c->Type, ' | null=', $c->Null, ' | dflt=', $c->Default ?? 'NONE', ' | key=', $c->Key, PHP_EOL;
}
?>