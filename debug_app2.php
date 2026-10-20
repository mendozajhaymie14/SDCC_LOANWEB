<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;

$conn = (new Application())->getConnection();
echo "Application connection: ", $conn->getDatabaseName(), PHP_EOL;
echo "Application table: ", (new Application())->getTable(), PHP_EOL;

echo "count(): ", Application::count(), PHP_EOL;
echo "all ids: ";
$all = Application::pluck('id');
foreach ($all as $id) { echo $id, ' '; }
echo PHP_EOL;

echo "Where id=12 toSql: ";
echo Application::where('id', 12)->toSql(), PHP_EOL;

echo "Where id=12 bindings: ";
var_dump(Application::where('id', 12)->getBindings());

echo "Exists id=12: ";
var_dump(Application::where('id', 12)->exists());

// Check SoftDeletes
echo "deleted_at for id=12 via raw: ";
$d = DB::table('applications')->where('id', 12)->value('deleted_at');
var_dump($d);
?>