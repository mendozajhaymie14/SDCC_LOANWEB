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

// Pick a pending application (id=10 is APP-0010, Pending, soft-deleted)
echo "=== Reject test for application ID: 10 (app_id: APP-0010)\n";
$application = DB::table('applications')
    ->where('status', 'Pending')
    ->whereNull('deleted_at')
    ->first();
if (!$application && DB::table('applications')->where('status', 'Pending')->exists()) {
    $application = DB::table('applications')
        ->where('status', 'Pending')
        ->orderBy('id', 'ASC')
        ->first();
}
if (!$application) {
    die("No pending applications found to test reject\n");
}
echo "Using ID: {$application->id} (app_id: {$application->app_id}) status was: "
    . ($application->deleted_at ? 'deleted' : 'not deleted') . "\n";

// Create mock request with JSON
$request = Request::create('/applications/'.$application->id.'/reject', 'POST', [
    'reason' => 'Fab rejection of over-requested amount',
], [], [], [], [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
]);

$controller = new AdminController();
try {
    $response = $controller->reject($application->id, $request);
    echo "Response type: ";
    echo get_class($response), "\n";
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        echo "Status: ", $response->getStatusCode(), "\n";
        echo json_encode($response->getData(true), JSON_PRETTY_PRINT), "\n";
    } else {
        var_dump($response);
    }

    // Check database after
    $appAfter = DB::table('applications')->where('id', $application->id)->first();
    $appAfterTrashed = DB::table('applications')
        ->where('id', $application->id)
        ->whereNull('deleted_at')->first();

    echo "\nDB row after reject:\n";
    echo "  applications.id = {$appAfter->id}\n";
    echo "  applications.status = {$appAfter->status}\n";
    echo "  applications.admin_remarks = ";
    var_export($appAfter->admin_remarks);
    echo "\n";
    echo "  loan_applications row exists: ";
    $loan = DB::table('loan_applications')->where('id', $application->loan_application_id)->first();
    echo ($loan ? 'yes' : 'no'), "\n";
} catch (Exception $e) {
    echo "Exception: ", $e->getMessage(), "\n";
    echo "Trace:\n", $e->getTraceAsString();
}
?>