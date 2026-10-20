<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;

echo "=== Testing FIXED applicationData for id=12 (with withTrashed) ===\n";
$application = Application::withTrashed()->where('app_id', 'APP-0012')->first()
    ?? Application::withTrashed()->where('id', 12)->first();

if (!$application) {
    echo "STILL NOT FOUND\n";
} else {
    echo "FOUND: id={$application->id} app_id={$application->app_id} status={$application->status}\n";
}

echo "\n=== Testing FIXED showApplication for numeric id 12 (with withTrashed) ===\n";
$app2 = Application::withTrashed()->where('app_id', 12)->first()
    ?? Application::withTrashed()->where('id', 12)->first()
    ?? Application::withTrashed()->where('loan_application_id', 12)->first();

if (!$app2) {
    echo "STILL NOT FOUND\n";
} else {
    echo "FOUND: id={$app2->id} app_id={$app2->app_id} status={$app2->status}\n";
}