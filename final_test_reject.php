<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AdminController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Find an admin user for auth
$admin = User::where('usertype', 'admin')->first();
if (!$admin) {
    die("No admin user found\n");
}
Auth::login($admin);

echo "=== REJECT END-TO-END TEST AGAINST MYSQL ===\n\n";

// Use APP-0005 (id=5) which is Approved and NOT soft-deleted
$appId = 5;
$appBefore = DB::table('applications')->where('id', $appId)->first();
$loanBefore = DB::table('loan_applications')->where('id', $appBefore->loan_application_id)->first();

echo "BEFORE:\n";
echo "  applications: id={$appBefore->id} app_id={$appBefore->app_id} status={$appBefore->status} approved_amount={$appBefore->approved_amount}\n";
$reviewedAt = $loanBefore->reviewed_at ?? 'NULL';
echo "  loan_applications: id={$loanBefore->id} status={$loanBefore->status} reviewed_at={$reviewedAt}\n";

// Convert application to Pending (simulate fresh submission)
DB::table('applications')->where('id', $appId)->update([
    'status' => 'Pending',
    'approved_amount' => null,
    'recommended_amount' => $appBefore->recommended_amount, // keep it
    // Note: remaining_balance etc may need reset but for test we leave as-is
]);

$appPending = DB::table('applications')->where('id', $appId)->first();
echo "  -> Set to Pending: status={$appPending->status}\n\n";

// Create mock request with JSON content
$jsonBody = json_encode(['reason' => 'Insufficient income documentation for loan purpose']);
$request = Request::create('/applications/'.$appId.'/reject', 'POST', [], [], [], [], $jsonBody);
$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');

// Bind mock request to container so `request()` returns it inside controller
app()->instance('request', $request);

$controller = new AdminController();
try {
    $response = $controller->reject($appId, $request);
    echo "RESPONSE:\n";
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        echo "  Status: {$response->getStatusCode()}\n";
        echo "  Body: ", json_encode($response->getData(true), JSON_PRETTY_PRINT), "\n";
    } else {
        echo "  Unexpected response: ", get_class($response), "\n";
        var_dump($response);
    }

    // Check database after
    $appAfter = DB::table('applications')->where('id', $appId)->first();
    $loanAfter = DB::table('loan_applications')->where('id', $appAfter->loan_application_id)->first();

    echo "\nAFTER (DB rows):\n";
    echo "  applications:\n";
    echo "    id              = {$appAfter->id}\n";
    echo "    app_id          = {$appAfter->app_id}\n";
    echo "    status          = {$appAfter->status}\n";
    echo "    admin_remarks   = ";
    var_export($appAfter->admin_remarks);
    echo "\n";
    echo "    approved_amount = {$appAfter->approved_amount}\n";
    echo "    recommended_amount = {$appAfter->recommended_amount}\n";
    echo "\n  loan_applications:\n";
    echo "    id              = {$loanAfter->id}\n";
    echo "    status          = {$loanAfter->status}\n";
    echo "    admin_remarks   = ";
    var_export($loanAfter->admin_remarks);
    echo "\n";
    $loanReviewedAt = $loanAfter->reviewed_at ?? 'NULL';
    echo "    reviewed_at     = {$loanReviewedAt}\n";

    // Final state check
    if ($appAfter->status === 'Rejected' && $loanAfter->status === 'rejected' && $appAfter->admin_remarks === 'Insufficient income documentation for loan purpose') {
        echo "\n✅ SUCCESS: Reject flow works correctly against MySQL.\n";
    } else {
        echo "\n❌ FAILURE: Unexpected state after reject.\n";
    }
} catch (Exception $e) {
    echo "❌ EXCEPTION: ", $e->getMessage(), "\n";
    echo "Trace:\n", $e->getTraceAsString();
}
?>