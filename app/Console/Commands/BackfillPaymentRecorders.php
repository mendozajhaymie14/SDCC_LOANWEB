<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPaymentRecorders extends Command
{
    protected $signature = 'sdcc:backfill-payment-recorders {--dry-run : Print what would change without writing} {--apply : Actually write changes}';
    protected $description = 'Backfill recorded_by on payments from audit_logs';

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
            $this->error("Refusing to run on protected database '$database'. Use --env=scratch for testing, or run manually with ALLOW_PROTECTED_DB=1 if absolutely necessary.");
            return 1;
        }

        // Find audit log entries for "Payment Recorded"
        $logs = AuditLog::where('action', 'Payment Recorded')
            ->whereNotNull('actor_id')
            ->get(['id', 'actor_id', 'details']);

        $this->info("Found {$logs->count()} 'Payment Recorded' audit log entries.");

        $updated = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            // Extract app_id from details: "Repayment of ₱1000.00 recorded for APP-0001"
            if (!preg_match('/Repayment of .+ recorded for\s+(APP-\d+)/', $log->details, $m)) {
                $this->warn("Could not parse app_id from audit log #{$log->id}: {$log->details}");
                $skipped++;
                continue;
            }
            $appId = $m[1];

            $payment = Payment::whereHas('application', function ($q) use ($appId) {
                $q->where('app_id', $appId);
            })->latest()->first();

            if (!$payment) {
                $this->warn("Payment for $appId not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            // Check if already has recorded_by
            if ($payment->recorded_by !== null) {
                $skipped++;
                continue;
            }

            $recorder = User::find($log->actor_id);
            if (!$recorder) {
                $this->warn("Actor user {$log->actor_id} not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            $this->line("  Payment #{$payment->id} ($appId): would set recorded_by = {$recorder->id} ({$recorder->name})");

            if ($apply) {
                $payment->update(['recorded_by' => $recorder->id]);
                $updated++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Payments to update: $updated");
        $this->line("  Skipped (already set / not found): $skipped");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --apply to write changes.');
        }

        return 0;
    }
}