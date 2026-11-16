<?php

namespace App\Console\Commands;

use App\Models\AppNotificationLog;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillNotifLogLoans extends Command
{
    protected $signature = 'sdcc:backfill-notif-log-loans {--dry-run : Print what would change without writing} {--apply : Actually write changes}';
    protected $description = 'Backfill loan_application_id on notification logs where it is missing but matchable with certainty';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $apply  = $this->option('apply');

        if ($dryRun && $apply) {
            $this->error('Use either --dry-run or --apply, not both.');
            return 1;
        }
        if (!$dryRun && !$apply) {
            $this->warn('Defaulting to --dry-run. Use --apply to write changes.');
            $dryRun = true;
        }

        $database = config('database.connections.' . config('database.default') . '.database');
        if (strtolower($database) === 'sdcc') {
            $this->error("Refusing to run on protected database '$database'. Use --env=scratch for testing.");
            return 1;
        }

        // Rows logged before loan_application_id was captured on the log table.
        $logs = AppNotificationLog::whereNull('loan_application_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'notification_type', 'created_at']);

        $this->info("Found {$logs->count()} notification-log rows missing loan_application_id.");

        $updated = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            // Matchable with certainty only when the user has exactly one loan
            // application — any other count is ambiguous and we leave "—".
            $loans = LoanApplication::where('user_id', $log->user_id)->get();

            if ($loans->count() !== 1) {
                $this->warn("Notification log #{$log->id} (user {$log->user_id}): " .
                    ($loans->count() === 0 ? 'no loans' : $loans->count() . ' loans') .
                    ' — ambiguous, leaving "—".');
                $skipped++;
                continue;
            }

            $loan = $loans->first();

            $this->line("  Notification log #{$log->id} (user {$log->user_id}, type {$log->notification_type}): " .
                ($apply ? 'set' : 'would set') . " loan_application_id = {$loan->id} (APP " . optional($loan->app_id) . ")");

            if ($apply) {
                $log->update(['loan_application_id' => $loan->id]);
                $updated++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Rows to update: $updated");
        $this->line("  Skipped (ambiguous / no loans): $skipped");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --apply to write changes.');
        }

        return 0;
    }
}
