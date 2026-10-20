<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;

echo "=== Testing applicationData for id=12 ===\n";
$application = Application::where('app_id', 'APP-0012')->first()
    ?? Application::where('id', 12)->first();

if (!$application) {
    echo "NOT FOUND (soft-deleted, excluded by default)\n";
} else {
    echo "Found: id={$application->id} app_id={$application->app_id} status={$application->status}\n";
}

echo "\n=== Testing showApplication logic for id=12 ===\n";
try {
    $app2 = Application::where('app_id', 12)->firstOrFail();
    echo "Found by app_id=12: id={$app2->id}\n";
} catch (\Exception $e) {
    echo "FAILED (app_id=12): ", $e->getMessage(), "\n";
}

echo "\n=== Pending apps count (excluding soft-deleted) ===\n";
echo Application::where('status', 'Pending')->count(), "\n";

echo "\n=== Pending apps count (including soft-deleted) ===\n";
echo Application::withTrashed()->where('status', 'Pending')->count(), "\n";
?>