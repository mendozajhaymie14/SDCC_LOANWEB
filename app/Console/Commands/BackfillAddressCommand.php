<?php

namespace App\Console\Commands;

use App\Models\MemberApplication;
use App\Models\CoopMember;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Backfill coop_members.address from member applications.
 *
 * Usage: php artisan backfill:address
 *
 * This command is dry-run by default. Add --force to actually update the database.
 * Only runs on sdcc_scratch database by default.
 */
class BackfillAddressCommand extends Command
{
    protected $name = 'backfill:address';
    protected $description = 'Backfill coop_members.address from member applications';

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Apply live updates to the database'],
            ['dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing'],
        ];
    }

    public function handle()
    {
        $force = $this->option('force');

        $this->info('Starting address backfill...');
        $this->info('Mode: ' . ($force ? 'Live update' : 'Dry run'));

        $applications = MemberApplication::whereNotNull('approved_member_id')
            ->where('status', 'approved')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($applications as $app) {
            $member = CoopMember::where('email', $app->email)->first();

            if (!$member) {
                $this->warn("No coop_member found for application #{$app->id} (email: {$app->email})");
                $skipped++;
                continue;
            }

            $changed = false;

            if (empty($member->address)) {
                // Compose address from application's address fields
                $addressParts = array_filter([
                    $app->house_no,
                    $app->street,
                    $app->barangay,
                    $app->municipality,
                    $app->province,
                    $app->zip_code,
                ]);

                if (!empty($addressParts)) {
                    $composedAddress = implode(', ', $addressParts);

                    if ($force) {
                        $member->address = $composedAddress;
                        $changed = true;
                        $this->info("Would update coop_member {$member->member_id} address to: {$composedAddress}");
                    } else {
                        $this->info("Would update coop_member {$member->member_id} address to: {$composedAddress}");
                    }
                } else {
                    $this->info("No address data in application #{$app->id}, skipping");
                }
            } else {
                $this->info("Coop_member {$member->member_id} already has address: {$member->address}");
            }

            // Backfill date_of_birth when empty and application has it
            if (empty($member->date_of_birth) && !empty($app->date_of_birth)) {
                if ($force) {
                    $member->date_of_birth = $app->date_of_birth;
                    $changed = true;
                    $this->info("Would update coop_member {$member->member_id} date_of_birth to: {$app->date_of_birth}");
                } else {
                    $this->info("Would update coop_member {$member->member_id} date_of_birth to: {$app->date_of_birth}");
                }
            }

            // Backfill savings_balance when empty and application has it
            if (empty($member->savings_balance) && !empty($app->savings_balance)) {
                if ($force) {
                    $member->savings_balance = $app->savings_balance;
                    $changed = true;
                    $this->info("Would update coop_member {$member->member_id} savings_balance to: {$app->savings_balance}");
                } else {
                    $this->info("Would update coop_member {$member->member_id} savings_balance to: {$app->savings_balance}");
                }
            }

            if ($force && $changed) {
                $member->save();
                $updated++;
            }
        }

        $this->info("Backfill complete: {$updated} updated, {$skipped} skipped");

        if (!$force) {
            $this->warn("Run with --force to apply changes to the database.");
        }

        return 0;
    }
}