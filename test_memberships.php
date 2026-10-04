<?php
// test_memberships.php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Clean up any leftover test rows
\App\Models\MemberApplication::truncate();

// Authenticate as an admin user
$admin = \App\Models\User::where('usertype', 'admin')->first();
if (!$admin) {
    $admin = new \App\Models\User();
    $admin->name = 'Test Admin';
    $admin->email = 'admin@test.com';
    $admin->password = bcrypt('password');
    $admin->forceFill(['usertype' => 'admin']);
    $admin->save();
}
app('auth')->guard('web')->login($admin);

// Create two test rows using the Eloquent model (handles columns properly)
$a1 = \App\Models\MemberApplication::create([
    'surname'          => 'Dela Cruz',
    'first_name'       => 'Juan',
    'middle_name'      => 'M',
    'email'            => 'juan@test.com',
    'contact_number'   => '09171234567',
    'birthdate'        => '1990-01-15',
    'nationality'      => 'Filipino',
    'place_of_birth'   => 'Manila',
    'gender'           => 'male',
    'occupation'       => 'Engineer',
    'civil_status'     => 'single',
    'residency_type'   => 'owned',
    'tin'              => '123-456-789-0000000',
    'status'           => 'pending',
]);

// The approved row references a coop_member, so create it first — the FK
// on approved_member_id would otherwise reject the insert.
$member = \App\Models\CoopMember::create([
    'member_id'    => 'SDCC-' . date('Y') . '-0001',
    'full_name'    => 'Maria Santos',
    'date_of_birth'=> '1985-05-20',
    'email'        => 'maria@test.com',
    'is_registered'=> false,
]);

$a2 = \App\Models\MemberApplication::create([
    'surname'           => 'Santos',
    'first_name'        => 'Maria',
    'email'             => 'maria@test.com',
    'contact_number'    => '09181234567',
    'birthdate'         => '1985-05-20',
    'nationality'       => 'Filipino',
    'place_of_birth'    => 'Quezon City',
    'gender'            => 'female',
    'occupation'        => 'Teacher',
    'civil_status'      => 'married',
    'residency_type'    => 'rented',
    'status'            => 'approved',
    'approved_member_id'=> $member->id,
]);

$request = Illuminate\Http\Request::create('/memberships', 'GET');
$session = $app->make('session.store');
$request->setLaravelSession($session);
$response = $kernel->handle($request);
$content = $response->getContent();

echo "HTTP Status: " . $response->getStatusCode() . "\n";
echo "Content length: " . strlen($content) . "\n";

// Look for actual error details in the Laravel error page
if (strpos($content, 'Exception') !== false || strpos($content, 'Error') !== false) {
    // Extract error from debug page
    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/', $content, $m)) {
        echo "Error title: " . $m[1] . "\n";
    }
    if (preg_match('/<span class="exception-message">([^<]+)<\/span>/', $content, $m)) {
        echo "Error message: " . $m[1] . "\n";
    }
    if (preg_match('/<pre[^>]*>([^<]+)<\/pre>/', $content, $m)) {
        echo "Error details: " . substr($m[1], 0, 500) . "\n";
    }
}

echo "Title present: " . (strpos($content, 'Membership Applications') !== false ? 'YES' : 'NO') . "\n";
echo "Table rows (2): " . (substr_count($content, 'class="applicant-name"') === 2 ? 'YES' : 'NO (' . substr_count($content, 'class="applicant-name"') . ')') . "\n";
echo "membersData: " . (strpos($content, 'membersData') !== false ? 'YES' : 'NO') . "\n";
echo "openMemberDetail: " . (strpos($content, 'openMemberDetail') !== false ? 'YES' : 'NO') . "\n";
echo "filterMembers: " . (strpos($content, 'filterMembers') !== false ? 'YES' : 'NO') . "\n";
echo "deleteMember: " . (strpos($content, 'deleteMember') !== false ? 'YES' : 'NO') . "\n";
echo "sidebar link: " . (strpos($content, 'Membership Applications') !== false ? 'YES' : 'NO') . "\n";

// Cleanup
$a1->delete();
$a2->delete();

$kernel->terminate($request, $response);
