<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\LoanApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ReconcileMirror extends Command
{
    protected $signature = 'sdcc:reconcile-mirror {--apply : Copy values from loan_applications to applications}';
    protected $description = 'Report drift between loan_applications and their mirrored applications; copies loan_applications → applications only (never reverse). Defaults to dry-run.';

    public function handle(): int
    {
        // Safety: print DB_DATABASE first, abort if real 'sdcc'
        $db = Config::get('database.connections.' . Config::get('database.default') . '.database');
        $this->info("DB_DATABASE: {$db}");
        if ($db === 'sdcc') {
            $this->error('ABORT: This command refuses to run against the real sdcc database.');
            return self::FAILURE;
        }

        // Orphans: mirrors with non-null loan_application_id where loan_application not found
        $orphans = Application::whereNotNull('loan_application_id')
            ->whereDoesntHave('loanApplication')
            ->get(['id', 'loan_application_id', 'app_id']);

        // Drift: matched pairs where values differ
        $apps = Application::whereNotNull('loan_application_id')->get();
        $drifted = [];

        foreach ($apps as $app) {
            $loan = LoanApplication::withTrashed()->find($app->loan_application_id);
            if (! $loan) {
                continue;
            }

            $drift = [];

            // status
            $loanStatus = \App\Support\LoanStatus::write($loan->status, 'applications');
            $appStatus = \App\Support\LoanStatus::write($app->status, 'applications');
            if ($loanStatus !== $appStatus) {
                $drift['status'] = ['loan' => $loanStatus, 'mirror' => $appStatus];
            }

            // amount
            if ((float) $loan->amount !== (float) $app->amount) {
                $drift['amount'] = ['loan' => (float) $loan->amount, 'mirror' => (float) $app->amount];
            }

            // approved_amount
            if ((float) ($loan->approved_amount ?? 0) !== (float) ($app->approved_amount ?? 0)) {
                $drift['approved_amount'] = ['loan' => (float) ($loan->approved_amount ?? 0), 'mirror' => (float) ($app->approved_amount ?? 0)];
            }

            // remaining_balance
            if ((float) ($loan->remaining_balance ?? 0) !== (float) ($app->remaining_balance ?? 0)) {
                $drift['remaining_balance'] = ['loan' => (float) ($loan->remaining_balance ?? 0), 'mirror' => (float) ($app->remaining_balance ?? 0)];
            }

            // next_due_date
            if ($loan->next_due_date !== $app->next_due_date) {
                $drift['next_due_date'] = ['loan' => $loan->next_due_date, 'mirror' => $app->next_due_date];
            }

            // risk_level
            if ($loan->risk_level !== $app->risk_level) {
                $drift['risk_level'] = ['loan' => $loan->risk_level, 'mirror' => $app->risk_level];
            }

            if (! empty($drift)) {
                $drifted[] = ['app' => $app, 'loan' => $loan, 'drift' => $drift];
            }
        }

        // Report orphans
        if ($orphans->isNotEmpty()) {
            $this->warn("Orphaned mirrors (loan_application_id with no matching loan_application):");
            foreach ($orphans as $o) {
                $this->line("  Application id={$o->id} app_id={$o->app_id} loan_application_id={$o->loan_application_id}");
            }
            $this->line('');
        }

        // Report drifts
        if (! empty($drifted)) {
            $this->warn("Drift between loan_applications and applications:");
            foreach ($drifted as $d) {
                $this->line("<info>Application id={$d['app']->id} app_id={$d['app']->app_id}</info>");
                foreach ($d['drift'] as $field => $vals) {
                    $this->line("  {$field}: loan={$vals['loan']} mirror={$vals['mirror']}");
                }
            }
        }

        if ($orphans->isEmpty() && empty($drifted)) {
            $this->info('No orphans or drifts found.');
        }

        // Apply mode: copy from loan_applications → applications only
        if ($this->option('apply') && ! empty($drifted)) {
            $this->warn('Applying fixes...');
            $applied = 0;
            foreach ($drifted as $d) {
                $app = $d['app'];
                $updates = [];

                foreach ($d['drift'] as $field => $vals) {
                    // Copy from loan_applications to applications
                    $loanField = 'loan->' . $field; // not used directly
                    // Determine the source from the $d['loan'] object
                    $source = match ($field) {
                        'status' => \App\Support\LoanStatus::write($d['loan']->status, 'applications'),
                        'amount' => $d['loan']->amount,
                        'approved_amount' => $d['loan']->approved_amount,
                        'remaining_balance' => $d['loan']->remaining_balance,
                        'next_due_date' => $d['loan']->next_due_date,
                        'risk_level' => $d['loan']->risk_level,
                        default => null,
                    };
                    if ($source !== null) {
                        $updates[$field] = $source;
                    }
                }

                if (! empty($updates)) {
                    $app->update($updates);
                    $applied++;
                }
            }
            $this->info("Applied {$applied} fixes.");
        } elseif ($this->option('apply')) {
            $this->info('No drifts to apply -- dry-run found nothing to fix.');
        }

        return self::SUCCESS;
    }
}