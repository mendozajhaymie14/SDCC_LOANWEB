<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillPaymentRecorders extends Command
{
    protected $signature = 'sdcc:backfill-payment-recorders {--dry-run : Print what would change without writing} {--apply : Actually write changes}';
    protected $description = 'Backfill encoded_by on payments from audit_logs';

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
            ->get(['id', 'actor_id', 'details', 'created_at']);

        $this->info("Found {$logs->count()} 'Payment Recorded' audit log entries.");

        $updated = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            // Details: "Repayment of ₱1000.00 recorded for APP-0001"
            if (!preg_match('/Repayment of ₱([\d,]+(?:\.\d{1,2})?) recorded for\s+(APP-\d+)/', $log->details, $m)) {
                $this->warn("Could not parse amount/app_id from audit log #{$log->id}: {$log->details}");
                $skipped++;
                continue;
            }
            $amount = (float) str_replace(',', '', $m[1]);
            $appId  = $m[2];

            // The audit log names the admin-side app_id; payments hang off the
            // loan_application_id, so resolve through the Application row.
            $application = Application::where('app_id', $appId)->first();
            if (!$application) {
                $this->warn("Application $appId not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            $candidates = Payment::where('loan_application_id', $application->loan_application_id)
                ->whereRaw('ABS(amount_paid - ?) < 0.005', [$amount])
                ->get();

            if ($candidates->count() > 1) {
                // Same amount paid more than once: narrow by the payment date
                // matching the audit entry's date, else call it ambiguous.
                $sameDay = $candidates->filter(fn ($p) => optional($p->payment_date)->format('Y-m-d') === $log->created_at->format('Y-m-d'));
                if ($sameDay->count() === 1) {
                    $candidates = $sameDay;
                }
            }

            if ($candidates->count() !== 1) {
                $this->warn("Ambiguous or missing payment for $appId / ₱$amount (audit log #{$log->id}): {$candidates->count()} match(es) — skipping");
                $skipped++;
                continue;
            }

            $payment = $candidates->first();

            if ($payment->encoded_by !== null) {
                $skipped++;
                continue;
            }

            $recorder = User::find($log->actor_id);
            if (!$recorder) {
                $this->warn("Actor user {$log->actor_id} not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            $this->line("  Payment #{$payment->id} ($appId, ₱$amount): " . ($apply ? 'set' : 'would set') . " encoded_by = {$recorder->id} ({$recorder->name})");

            if ($apply) {
                $payment->update(['encoded_by' => $recorder->id]);
                $updated++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Payments to update: $updated");
        $this->line("  Skipped (already set / not found / ambiguous): $skipped");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --apply to write changes.');
        }

        return 0;
    }
}
