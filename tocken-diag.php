<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Query 1: coop_members.date_of_birth for SDCC-2023-0002
echo "DB_DATABASE: " . config('database.connections.mysql.database') . "\n";
echo "DB_CONNECTION: " . config('database.default') . "\n";
echo "APP_ENV: " . env('APP_ENV') . "\n\n";

echo "coop_members.date_of_birth for SDCC-2023-0002: ";
$db = App\Models\CoopMember::where('member_id', 'SDCC-2023-0002')->first();
echo $db->date_of_birth ?? 'NULL';
echo "\n";

echo "coop_members.date_of_birth for SDCC-2023-0002 (app only): ";
$db2 = \Illuminate\Support\Facades\DB::table('coop_members')->where('member_id', 'SDCC-2023-0002')->value('date_of_birth');
echo $db2 ?? 'NULL';
echo "\n";

echo "\nAll coop_members with date_of_birth:\n";
foreach (\Illuminate\Support\Facades\DB::table('coop_members')->get() as $m) {
    echo "  {$m->member_id}: {$m->date_of_birth}\n";
}

echo "\nAll member_applications with date_of_birth:\n";
foreach (\Illuminate\Support\Facades\DB::table('member_applications')->whereNotNull('date_of_birth')->get() as $a) {
    echo "  {$a->id} ($a->email): {$a->date_of_birth}\n";
}
