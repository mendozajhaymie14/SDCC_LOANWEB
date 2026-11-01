<?php

namespace App\Services;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Support\LoanStatus;
use App\Services\AmortizationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Single source-of-truth service for loan repayment state.
 * All three controller methods (storeRepayment, updateRepayment,
 * destroyRepayment) delegate to recomputeLoanState() so that
 * remaining_balance, next_due_date, and loan status are always
 * consistent across the two parallel tables (applications / loan_applications).
 */
class PaymentService
{
    /** @var AmortizationService */
    protected $amortization;

    /**
     * Create a new service instance.
     *
     * @param  AmortizationService  $amortization
     */
    public function __construct(AmortizationService $amortization)
    {
        $this->amortization = $amortization;
    }

    /**
     * Recompute the loan state after a payment is stored/updated/deleted.
     *
     * - total_paid = sum of all payments (excluding $excludePayment if provided)
     * - remaining_balance = total_payable - total_paid
     * - installments_covered = floor(total_paid / monthly_payment)
     * - next_due_date = first_due_date + installments_covered months
     * - If remaining_balance <= 0: status → PAID, next_due_date → null
     * - If remaining_balance > 0 and status was PAID: revert → APPROVED
     * - remaining_balance_after on each Payment is recomputed as a running total:
     *   after-payment-1 = total_payable - payment-1-amount,
     *   after-payment-2 = after-payment-1 - payment-2-amount, etc.
     *
     * Updates both Application and LoanApplication rows in one transaction
     * with lockForUpdate on the loan row.
     *
     * @param  \App\Models\LoanApplication  $loan
     * @param  \App\Models\Payment|null  $excludePayment  Payment to exclude from total_paid (for edit/delete)
     * @return void
     */
    public function recomputeLoanState(LoanApplication $loan, ?Payment $excludePayment = null): void
    {
        DB::transaction(function () use ($loan, $excludePayment) {
            // Reload loan with lockForUpdate inside the transaction
            $loan = LoanApplication::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            // total_payable = principal + interest (NOT raw principal)
            $totalPayable = $loan->totalPayable();

            // Sum all payments for this loan, excluding the one we're editing/deleting
            $totalPaid = Payment::where('loan_application_id', $loan->id)
                ->when($excludePayment, function ($q) use ($excludePayment) {
                    $q->where('id', '!=', $excludePayment->id);
                })
                ->sum('amount_paid');

            // If the loan has a related Application row, also recompute that
            $application = Application::where('loan_application_id', $loan->id)->first();

            // Monthly payment from AmortizationService
            $monthlyPayment = $this->amortization->emi(
                $totalPayable,
                $loan->term_months ?? 12,
                $loan->interest_rate ?? 0
            );

            // Installments covered: floor(total_paid / monthly_payment)
            // Guard against zero/invalid monthly payment
            if ($monthlyPayment <= 0) {
                $installmentsCovered = 0;
            } else {
                $installmentsCovered = (int)floor($totalPaid / $monthlyPayment);
            }

            // First due date: loan's next_due_date if set, else disbursement_date if set, else created_at end-of-month
            $firstDueDate = null;
            if ($loan->next_due_date) {
                $firstDueDate = Carbon::parse($loan->next_due_date);
            } elseif ($loan->disbursement_date) {
                $firstDueDate = Carbon::parse($loan->disbursement_date);
            } else {
                // Use created_at end-of-month as fallback
                $firstDueDate = Carbon::parse($loan->created_at)->endOfMonth();
            }

            // Calculate next_due_date
            $nextDueDate = null;
            if ($installmentsCovered > 0) {
                $nextDueDate = $firstDueDate->copy()->addMonths($installmentsCovered)->format('Y-m-d');
            } elseif ($firstDueDate) {
                // No installments covered yet — preserve the original due date
                $nextDueDate = $firstDueDate->format('Y-m-d');
            }

            // Determine new status
            $newStatus = null;
            if ($totalPaid >= $totalPayable) {
                // Fully paid or overpaid → PAID
                $newStatus = LoanStatus::PAID;
                // When fully paid, clear the next_due_date
                $nextDueDate = null;
            } elseif ($loan->status === LoanStatus::PAID && $totalPaid < $totalPayable) {
                // Loan was PAID but a payment was deleted/restored → revert to APPROVED
                $newStatus = LoanStatus::APPROVED;
            } else {
                // Keep existing status (PENDING / APPROVED / REJECTED / FLAGGED)
                $newStatus = $loan->status;
            }

            // Update LoanApplication row
            $loan->remaining_balance = max(0, $totalPayable - $totalPaid);
            $loan->next_due_date = $nextDueDate;
            $loan->status = $newStatus;
            $loan->save();

            // Update Application row if it exists, keeping the same logic
            if ($application) {
                $application->remaining_balance = max(0, $totalPayable - $totalPaid);
                $application->next_due_date = $nextDueDate;
                // Application uses PascalCase status via LoanStatus::write()
                $application->status = LoanStatus::write($newStatus, 'applications');
                $application->save();
            }

            // Update remaining_balance_after running total on all Payments for this loan
            // Compute running balance starting from total_payable, subtracting each payment's amount_paid in chronological order
            $payments = Payment::where('loan_application_id', $loan->id)
                ->orderBy('payment_date', 'asc')
                ->get();

            $runningBalance = $totalPayable;
            foreach ($payments as $pay) {
                $runningBalance -= $pay->amount_paid;
                $pay->remaining_balance_after = max(0, $runningBalance);
                $pay->save();
            }
        });
    }
}