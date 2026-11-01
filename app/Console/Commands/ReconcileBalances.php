<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBalances extends Command
{
    protected $signature = 'sdcc:reconcile-balances {--dry-run : Show discrepancies without applying fixes}';
    protected $description = 'Recompute remaining_balance and next_due_date for all loans using PaymentService (total_payable = principal + interest)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $paymentService = app(PaymentService::class);

        $this->info('Starting balance reconciliation...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - no changes will be persisted');
        }

        $loans = LoanApplication::whereNotNull('id')->get();
        $this->info("Found {$loans->count()} loan applications to check.");

        $discrepancies = 0;
        $fixed = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($loans->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        foreach ($loans as $loan) {
            try {
                // Compute what PaymentService would produce
                $totalPayable = $loan->totalPayable();
                $totalPaid = Payment::where('loan_application_id', $loan->id)->sum('amount_paid');
                $expectedRemaining = max(0, $totalPayable - $totalPaid);

                // Current values
                $currentRemaining = (float) ($loan->remaining_balance ?? 0);
                $currentNextDue = $loan->next_due_date;

                // Check for discrepancy
                $hasDiscrepancy = abs($currentRemaining - $expectedRemaining) > 0.01;

                // Also check next_due_date discrepancy
                $monthlyPayment = $paymentService->getAmortization()->emi(
                    $totalPayable,
                    $loan->term_months ?? 12,
                    $loan->interest_rate ?? 0
                );
                $installmentsCovered = $monthlyPayment > 0 ? (int) floor($totalPaid / $monthlyPayment) : 0;

                $firstDueDate = null;
                if ($loan->next_due_date) {
                    $firstDueDate = \Carbon\Carbon::parse($loan->next_due_date);
                } elseif ($loan->disbursement_date) {
                    $firstDueDate = \Carbon\Carbon::parse($loan->disbursement_date);
                } else {
                    $firstDueDate = \Carbon\Carbon::parse($loan->created_at)->endOfMonth();
                }

                $expectedNextDue = null;
                if ($totalPaid >= $totalPayable) {
                    $expectedNextDue = null;
                } elseif ($installmentsCovered > 0) {
                    $expectedNextDue = $firstDueDate->copy()->addMonths($installmentsCovered)->format('Y-m-d');
                } elseif ($firstDueDate) {
                    $expectedNextDue = $firstDueDate->format('Y-m-d');
                }

                $nextDueMismatch = $currentNextDue != $expectedNextDue;

                if ($hasDiscrepancy || $nextDueMismatch) {
                    $discrepancies++;
                    $this->progressBar->clear();

                    $this->line("");
                    $this->line("<info>Loan ID: {$loan->id} (APP-{$loan->id})</info>");
                    if ($hasDiscrepancy) {
                        $this->line("  remaining_balance: current={$currentRemaining} expected={$expectedRemaining} diff=" . ($currentRemaining - $expectedRemaining));
                    }
                    if ($nextDueMismatch) {
                        $this->line("  next_due_date: current={$currentNextDue} expected={$expectedNextDue}");
                    }

                    if (! $dryRun) {
                        DB::transaction(function () use ($loan, $paymentService) {
                            $paymentService->recomputeLoanState($loan);
                        });
                        $fixed++;
                        $this->line("  <comment>Fixed</comment>");
                    } else {
                        $this->line("  <comment>Would fix</comment>");
                    }
                    $this->progressBar->display();
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->progressBar->clear();
                $this->error("Error processing loan {$loan->id}: {$e->getMessage()}");
                $this->progressBar->display();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Also check Application table for consistency
        $this->info('Checking Application table consistency...');
        $apps = Application::whereNotNull('loan_application_id')->get();
        $appDiscrepancies = 0;

        foreach ($apps as $app) {
            $loan = LoanApplication::find($app->loan_application_id);
            if (! $loan) {
                continue;
            }

            $loanRemaining = (float) ($loan->remaining_balance ?? 0);
            $appRemaining = (float) ($app->remaining_balance ?? 0);

            if (abs($loanRemaining - $appRemaining) > 0.01) {
                $appDiscrepancies++;
                $this->line("<info>Application ID: {$app->id} (loan_application_id={$app->loan_application_id})</info>");
                $this->line("  LoanApplication remaining_balance: {$loanRemaining}");
                $this->line("  Application remaining_balance: {$appRemaining}");

                if (! $dryRun) {
                    $app->remaining_balance = $loanRemaining;
                    $app->next_due_date = $loan->next_due_date;
                    // Sync status using LoanStatus::write
                    $app->status = \App\Support\LoanStatus::write($loan->status, 'applications');
                    $app->save();
                    $this->line("  <comment>Synced with LoanApplication</comment>");
                } else {
                    $this->line("  <comment>Would sync</comment>");
                }
            }
        }

        $this->newLine();
        $this->info("=== Reconciliation Summary ===");
        $this->info("Loans checked: {$loans->count()}");
        $this->info("Discrepancies found: {$discrepancies}");
        $this->info("Application discrepancies: {$appDiscrepancies}");
        $this->info("Errors: {$errors}");

        if (! $dryRun) {
            $this->info("Fixed: {$fixed}");
        } else {
            $this->warn("Run without --dry-run to apply fixes.");
        }

        return self::SUCCESS;
    }
}