<?php

namespace App\Console\Commands;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MembersSyncApproved extends Command
{
    protected $signature = 'members:sync-approved {--dry-run : Show what would be fixed without applying changes}';
    protected $description = 'Find approved membership applications whose linked coop member is not active and fix them';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Scanning for approved applications with non-active member rows...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - no changes will be persisted');
        }

        // Approved applications whose linked member is not active (status != active
        // OR is_registered != true). Approved applications without any linked row
        // are left alone.
        $applications = MemberApplication::where('status', 'approved')->get();

        if ($applications->isEmpty()) {
            $this->info('No stale approved applications found.');
            return self::SUCCESS;
        }

        $this->info("Found {$applications->count()} approved application(s) with non-active member rows.");
        $this->newLine();

        $fixed = 0;
        $progressBar = $this->output->createProgressBar($applications->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        foreach ($applications as $application) {
            $member = null;

            if ($application->approved_member_id) {
                $member = CoopMember::find($application->approved_member_id);
            }
            if (! $member) {
                $member = CoopMember::where('email', $application->email)->first();
            }

            if (! $member) {
                $progressBar->clear();
                $this->line("<error>No linked member found for application #{$application->id} ({$application->email})</error>");
                $progressBar->display();
                continue;
            }

            $needsFix = $member->status !== CoopMember::STATUS_ACTIVE || ! $member->is_registered;

            if (! $needsFix) {
                $progressBar->advance();
                continue;
            }

            $progressBar->clear();
            $this->line("<info>Application #{$application->id}</info> → Member {$member->member_id} ({$member->full_name})");
            $this->line("  status: {$member->status} → " . CoopMember::STATUS_ACTIVE);
            $this->line("  is_registered: " . ($member->is_registered ? 'true' : 'false') . " → true");

            if (empty($member->email) && $application->email) {
                $this->line("  email: (empty) → {$application->email}");
            }
            if (empty($member->contact_number) && $application->contact_number) {
                $this->line("  contact_number: (empty) → {$application->contact_number}");
            }
            if (empty($member->monthly_income) && $application->monthly_income) {
                $this->line("  monthly_income: (empty) → {$application->monthly_income}");
            }
            if (empty($member->existing_monthly_obligations) && $application->existing_monthly_obligations) {
                $this->line("  existing_monthly_obligations: (empty) → {$application->existing_monthly_obligations}");
            }

            if (! $dryRun) {
                DB::transaction(function () use ($member, $application) {
                    if (empty($member->email) && $application->email) {
                        $member->email = $application->email;
                    }
                    if (empty($member->contact_number) && $application->contact_number) {
                        $member->contact_number = $application->contact_number;
                    }
                    if (empty($member->monthly_income) && $application->monthly_income) {
                        $member->monthly_income = $application->monthly_income;
                    }
                    if (empty($member->existing_monthly_obligations) && $application->existing_monthly_obligations) {
                        $member->existing_monthly_obligations = $application->existing_monthly_obligations;
                    }

                    $member->status        = CoopMember::STATUS_ACTIVE;
                    $member->is_registered  = true;
                    $member->save();

                    // Ensure application points to the fixed member
                    if ($application->approved_member_id !== $member->id) {
                        $application->approved_member_id = $member->id;
                        $application->save();
                    }
                });
                $fixed++;
                $this->line("  <comment>Fixed</comment>");
            } else {
                $this->line("  <comment>Would fix</comment>");
            }

            $progressBar->display();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("=== Sync Summary ===");
        $this->info("Applications checked: {$applications->count()}");
        if ($dryRun) {
            $this->info("Would fix: {$fixed}");
            $this->warn("Run without --dry-run to apply fixes.");
        } else {
            $this->info("Fixed: {$fixed}");
        }

        return self::SUCCESS;
    }
}