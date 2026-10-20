<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$apps = DB::select("SELECT id, app_id, applicant, status FROM applications WHERE status = 'Pending'");
foreach($apps as $a){
    echo $a->id, ' | ', $a->app_id, ' | ', $a->applicant, ' | ', $a->status, PHP_EOL;
}
?>