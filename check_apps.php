<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apps = DB::select("SELECT id, app_id, applicant, status, deleted_at FROM applications WHERE status IN ('Pending','Approved')");
foreach($apps as $r){
    echo $r->id, ' | ', $r->app_id, ' | ', $r->status, ' | del=', $r->deleted_at ?? 'NULL', PHP_EOL;
}
?>