<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = config('database.connections.' . config('database.default') . '.database');
if ($database !== 'sdcc_scratch') {
    fwrite(STDERR, "Refusing to touch database [{$database}]. Only sdcc_scratch may be used.\n");
    exit(1);
}

use App\Models\User;
use App\Support\Permissions;

$user = User::updateOrCreate(
    ['email' => 'admin-test@sdcc.coop'],
    [
        'name' => 'Admin Test User',
        'password' => password_hash('admin-test-123', PASSWORD_DEFAULT),
        'usertype' => Permissions::ROLE_BRANCH_MANAGER,
        'role' => Permissions::ROLE_BRANCH_MANAGER,
    ]
);

echo json_encode([
    'email' => $user->email,
    'password' => 'admin-test-123',
    'database' => $database,
]) . PHP_EOL;