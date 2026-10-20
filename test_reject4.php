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

// Re-create a pending application id=16 by restoring it
$appRow = DB::table('applications')->where('id', 16)->first();
echo "Before reject, id=16 status: ", $appRow->status, " (deleted_at=", $appRow->deleted_at ?? 'NULL', ")\n";
if ($appRow->status !== 'Pending') {
    DB::table('applications')->where('id', 16)->update([
        'status' => 'Pending',
        'approved_amount' => null,
        'remaining_balance' => $appRow->remaining_balance,
        'next_due_date' => null,
        'disbursement_date' => null,
    ]);
}

// Create mock request WITH JSON content body (7th param is server array, 8th is content)
$request = Request::create('/applications/16/reject', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT'  => 'application/json',
], json_encode(['reason' => 'Fab rejection of over-requested amount']));

$controller = new AdminController();
try {
    $response = $controller->reject(16, $request);
    echo "Response status: ";
    echo $response instanceof \Illuminate\Http\JsonResponse ? $response->getStatusCode() : get_class($response), "\n";
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        echo json_encode($response->getData(true), JSON_PRETTY_PRINT), "\n";
    }

    // Check database after
    $appAfter = DB::table('applications')->where('id', 16)->first();
    echo "\nDB row after reject:\n";
    echo "  applications.id       = {$appAfter->id}\n";
    echo "  applications.status   = {$appAfter->status}\n";
    echo "  applications.admin_remarks = ";
    var_export($appAfter->admin_remarks);
    echo "\n";
    echo "  applications.app_id   = {$appAfter->app_id}\n";
    echo "  applications.applicant= {$appAfter->applicant}\n";

    $loanAfter = DB::table('loan_applications')->where('id', $appRow->loan_application_id ?? null)->first();
    if ($loanAfter) {
        echo "  loan_applications.status   = {$loanAfter->status}\n";
        echo "  loan_applications.reviewed_at = ", $loanAfter->reviewed_at ?? 'NULL', "\n";
    } else {
        echo "  loan_applications row NOT found\n";
    }
} catch (Exception $e) {
    echo "Exception: ", $e->getMessage(), "\n";
    echo "Trace:\n", $e->getTraceAsString();
}
?>