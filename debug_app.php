<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;

echo "DB connection: ", config('database.default'), " | ", config('database.connections.' . config('database.default') . '.host'), PHP_EOL;

echo "Query via DB::table: ";
$row = DB::table('applications')->where('id', 12)->first();
if ($row) {
    echo "found id=12", PHP_EOL;
} else {
    echo "NOT found (empty)", PHP_EOL;
}

echo "Query via Model: ";
try {
    $m = Application::where('id', 12)->first();
    if ($m) {
        echo "model found id=12 status=" . $m->status, PHP_EOL;
    } else {
        echo "model NOT found (empty)", PHP_EOL;
    }
} catch (\Exception $e) {
    echo "exception: ", $e->getMessage(), PHP_EOL;
}

echo "Column list count: ";
$cols = DB::select("SELECT COUNT(*) as c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'applications'");
echo $cols[0]->c, PHP_EOL;

echo "Raw query: ";
$q = DB::select("SELECT id, app_id, applicant, status FROM applications WHERE id = 12");
foreach ($q as $r) {
    echo "id=", $r->id, " app_id=", $r->app_id, " applicant=", $r->applicant, " status=", $r->status, PHP_EOL;
}
?>