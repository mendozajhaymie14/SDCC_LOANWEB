<?php

namespace App\Console\Commands;

use App\Models\LoanApplication;
use App\Models\Payment;
use Illuminate\Console\Command;

class BackfillPaymentLateness extends Command
{
    protected $signature = 'sdcc:backfill-payment-lateness {--dry-run : Print what would change without writing} {--apply : Actually write changes}';
    protected $description = 'Backfill due_date and days_late on payments recorded before the snapshot columns existed';

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

        $payments = Payment::whereNull('due_date')
            ->with('loanApplication')
            ->orderBy('id')
            ->get();

        $this->info("Found {$payments->count()} payment(s) without a due_date snapshot.");

        $updated = 0;
        $skipped = 0;

        foreach ($payments as $p) {
            $loan = $p->loanApplication;
            if (!$loan) {
                $this->warn("  Payment #{$p->id}: no loan application — skipping");
                $skipped++;
                continue;
            }

            $pd = $p->payment_date;
            if (!$pd) {
                $this->warn("  Payment #{$p->id}: no payment date — skipping");
                $skipped++;
                continue;
            }

            // Anchor per-payment due dates backwards from the loan's CURRENT
            // next_due_date, exactly as the report's legacy fallback does, so
            // the backfilled values agree with what the report would compute.
            $nextDue = $loan->next_due_date;
            if (!$nextDue) {
                $firstDueDate = $loan->disbursement_date
                    ? \Carbon\Carbon::parse($loan->disbursement_date)
                    : \Carbon\Carbon::parse($loan->created_at)->endOfMonth();
                $anchorPaid = (float) $loan->totalPaid();
                $emi = (float) $loan->monthly_payment;
                $installmentsCovered = $emi > 0 ? (int) floor($anchorPaid / $emi) : 0;
                $computedNextDue = $firstDueDate->copy();
                if ($installmentsCovered > 0) {
                    $computedNextDue = $computedNextDue->addMonths($installmentsCovered);
                }
                $nextDue = $computedNextDue->format('Y-m-d');
            }

            // next_due_date is the due date for installment (paymentCount + 1),
            // so the payment at 0-based index i is due at
            // next_due_date - (paymentCount - i) months. This mirrors the
            // report's legacy fallback exactly.
            $allPayments = Payment::where('loan_application_id', $loan->id)
                ->orderBy('payment_date', 'asc')
                ->orderBy('id', 'asc')
                ->get(['id']);
            $paymentCount = $allPayments->count();
            $index = $allPayments->search(fn ($row) => $row->id === $p->id);

            $dueDate = \Carbon\Carbon::parse($nextDue)
                ->subMonths($paymentCount - $index)
                ->startOfDay();

            // Carbon 3: $a->diffInDays($b) returns $b - $a. We need dueDate->diffInDays(pd)
            // to get positive when payment is after due.
            $daysLate = max(0, (int) $dueDate->diffInDays($pd->startOfDay(), false));

            $this->line("  Payment #{$p->id} (loan #{$loan->id}): due {$dueDate->format('Y-m-d')}, days_late = {$daysLate}" . ($apply ? '' : ' [dry-run]'));

            if ($apply) {
                $p->update(['due_date' => $dueDate->format('Y-m-d'), 'days_late' => $daysLate]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Payments to update: $updated");
        $this->line("  Skipped (dry-run / no loan / no date): $skipped");

        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --apply to write changes.');
        }

        return 0;
    }
}