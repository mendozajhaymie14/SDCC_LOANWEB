<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AdminController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Find an admin user for auth
$admin = User::where('usertype', 'admin')->first();
if (!$admin) {
    die("No admin user found\n");
}
Auth::login($admin);

// Find a pending application (not soft-deleted)
$application = DB::table('applications')
    ->where('status', 'Pending')
    ->whereNull('deleted_at')
    ->first();
if (!$application) {
    die("No pending applications found\n");
}
echo "Testing reject on application ID: {$application->id} (app_id: {$application->app_id})\n";

// Create mock request with JSON
$request = Request::create('/applications/'.$application->id.'/reject', 'POST', [
    'reason' => 'Test rejection via script',
], [], [], [], [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
]);

// Instantiate controller and call reject
$controller = new AdminController();
try {
    $response = $controller->reject($application->id, $request);
    echo "Response status: ";
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        echo $response->getStatusCode(), PHP_EOL;
        echo json_encode($response->getData(true), JSON_PRETTY_PRINT), PHP_EOL;
    } else {
        var_dump($response);
    }

    // Check database after
    $appAfter = DB::table('applications')->where('id', $application->id)->first();
    echo "After reject: status = {$appAfter->status}, admin_remarks = ";
    var_dump($appAfter->admin_remarks);
} catch (Exception $e) {
    echo "Exception: ", $e->getMessage(), "\n";
    echo "Trace:\n", $e->getTraceAsString();
}