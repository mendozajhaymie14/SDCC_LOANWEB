<?php

/**
 * Fixture for the browser test: one cooperative member who can be verified
 * from the registration form.
 *
 * Run with APP_ENV=scratch. The script refuses to touch any database other
 * than the scratch one and prints a single JSON line for the test runner.
 */

use App\Models\CoopMember;

require __DIR__ . '/../../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = config('database.connections.' . config('database.default') . '.database');

if ($database !== 'sdcc_scratch') {
    fwrite(STDERR, "Refusing to touch database [{$database}]. Only sdcc_scratch may be used.\n");
    exit(1);
}

$memberId = 'SDCC-BROWSER-TEST-0001';

$member = CoopMember::where('member_id', $memberId)->first();

if ($member) {
    $member->forceFill([
        'full_name'      => 'Juan Dela Cruz',
        'email'          => 'juan.browsertest@gmail.com',
        'contact_number' => '09171234567',
        'is_registered'  => false,
        'status'         => CoopMember::STATUS_ACTIVE,
    ])->save();
    $member->user_id = null;
    $member->save();
} else {
    CoopMember::create([
        'member_id'      => $memberId,
        'full_name'      => 'Juan Dela Cruz',
        'date_of_birth'  => '1985-03-14',
        'email'          => 'juan.browsertest@gmail.com',
        'contact_number' => '09171234567',
        'is_registered'  => false,
        'status'         => CoopMember::STATUS_ACTIVE,
    ]);
}

echo json_encode([
    'member_id' => $memberId,
    'database'  => $database,
]) . PHP_EOL;
