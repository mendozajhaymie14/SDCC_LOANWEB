<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillApprovers extends Command
{
    protected $signature = 'sdcc:backfill-approvers {--dry-run : Print what would change without writing} {--apply : Actually write changes}';
    protected $description = 'Backfill approved_by on applications and loan_applications from audit_logs';

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

        // Find audit log entries for "Application Approved"
        $logs = AuditLog::where('action', 'Application Approved')
            ->whereNotNull('actor_id')
            ->get(['id', 'actor_id', 'details']);

        $this->info("Found {$logs->count()} 'Application Approved' audit log entries.");

        $updatedApps = 0;
        $updatedLoans = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            // Extract app_id from details: "Application APP-0001 approved for ..."
            if (!preg_match('/Application\s+(APP-\d+)\s+approved/', $log->details, $m)) {
                $this->warn("Could not parse app_id from audit log #{$log->id}: {$log->details}");
                $skipped++;
                continue;
            }
            $appId = $m[1];

            $application = Application::where('app_id', $appId)->first();
            if (!$application) {
                $this->warn("Application $appId not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            $loanApp = $application->loan_application_id
                ? LoanApplication::find($application->loan_application_id)
                : null;

            // Check if already has approved_by
            $appAlreadySet   = $application->approved_by !== null;
            $loanAlreadySet  = $loanApp && $loanApp->approved_by !== null;

            if ($appAlreadySet && $loanAlreadySet) {
                $skipped++;
                continue;
            }

            $approver = User::find($log->actor_id);
            if (!$approver) {
                $this->warn("Actor user {$log->actor_id} not found for audit log #{$log->id}");
                $skipped++;
                continue;
            }

            $this->line("  $appId: would set approved_by = {$approver->id} ({$approver->name})");

            if ($apply) {
                if (!$appAlreadySet) {
                    $application->update(['approved_by' => $approver->id]);
                    $updatedApps++;
                }
                if ($loanApp && !$loanAlreadySet) {
                    $loanApp->update(['approved_by' => $approver->id]);
                    $updatedLoans++;
                }
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Applications to update: $updatedApps");
        $this->line("  LoanApplications to update: $updatedLoans");
        $this->line("  Skipped (already set / not found): $skipped");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --apply to write changes.');
        }

        return 0;
    }
}