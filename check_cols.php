<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM applications WHERE Field LIKE 'admin%' OR Field LIKE 'reason'");
foreach($cols as $c){
    echo $c->Field, ' | ', $c->Type, " | null=", ($c->Null==='YES'?'YES':'NO'), " | dflt=", $c->Default ?? 'NONE', " | pk=", $c->Key===''?'no':$c->Key, PHP_EOL;
}
?>