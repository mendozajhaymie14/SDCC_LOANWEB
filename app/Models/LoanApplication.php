<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\AmortizationService;
use Illuminate\Support\Facades\DB;

class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    // Explicit, so the table name never again depends on how the class
    // happens to be capitalised.
    protected $table = 'loan_applications';

    protected $fillable = [
        'user_id', 'reference', 'full_name', 'email', 'contact_number', 'address',
        'birth_date', 'employment_status', 'employer_name',
        'source_of_income', 'monthly_income', 'existing_monthly_obligations',
        'loan_type', 'purpose_of_loan', 'amount', 'share_capital',
        'documents', 'term_months', 'status', 'admin_remarks', 'reviewed_at',
        'remaining_balance', 'next_due_date',
        'recommended_amount', 'risk_level', 'risk_score', 'risk_factors',
        'document_check',
        // AI scoring fields (added for synchronous fallback)
        'ai_score', 'ai_analysis_notes', 'extracted_document_data', 'document_processing_status',
    ];

    protected $casts = [
        'birth_date'        => 'date',
        'reviewed_at'       => 'datetime',
        'documents'         => 'array',
        'amount'            => 'decimal:2',
        'share_capital'     => 'decimal:2',
        'monthly_income'    => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'recommended_amount'=> 'decimal:2',
        'next_due_date'     => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Monthly payment — delegated to AmortizationService.
     */
    public function getMonthlyPaymentAttribute(): float
    {
        return app(AmortizationService::class)
            ->emi((float) $this->amount, (int) ($this->term_months ?? 12));
    }

    /**
     * Total payable = principal + interest over the full term.
     * This is the ONLY correct base for remaining_balance — never the raw principal.
     */
    public function totalPayable(): float
    {
        return (float) app(AmortizationService::class)
            ->summary((float) $this->amount, (int) ($this->term_months ?? 12))['total_paid'];
    }

    /**
     * Remaining balance as total payable minus sum of payments.
     * Used by PaymentService::recomputeLoanState() as single source of truth.
     */
    public function remainingBalance(): float
    {
        $paid = $this->payments()->sum('amount_paid');
        return max(0, $this->totalPayable() - (float) $paid);
    }

    /**
     * Payments on this loan, ordered chronologically.
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class, 'loan_application_id')
            ->orderBy('payment_date', 'asc');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved'   => 'Approved',
            'rejected'   => 'Rejected',
            default      => 'Under review',
        };
    }

    /**
     * Human-readable applicant name, formatted as "Last, First Middle".
     * Handles Filipino compound surnames (Dela Cruz, Van der, Mc, etc.)
     * Falls back to applicant field if no user relationship.
     */
    public function fullName(): string
    {
        $user = $this->user;
        if ($user) {
            $last = $user->last_name ?? '';
            $first = $user->first_name ?? '';
            $middle = $user->middle_name ?? '';

            // If middle_name starts with a compound-surname prefix, fold it
            // (and any remaining parts) into last_name so "Dela Cruz" stays together.
            $compoundPrefixes = ['De', 'Dela', 'Del', 'Van', 'Von', 'Mc', 'Mac', 'Di', 'Da'];
            $middleParts = trim($middle) ? explode(' ', trim($middle)) : [];
            $firstMiddleParts = array_filter([trim($first), trim($middle)]);

            if ($middleParts && in_array(mb_ucfirst($middleParts[0]), $compoundPrefixes, true)) {
                // Middle starts with compound prefix → everything in middle + last goes to last name
                $combinedLast = trim($middle . ' ' . $last);
                $combinedFirst = $first;
                if ($combinedLast && $combinedFirst) {
                    return $combinedLast . ', ' . $combinedFirst;
                }
                if ($combinedLast) return $combinedLast;
            }

            $firstMiddle = trim($first . ' ' . $middle);
            if ($last && $firstMiddle) {
                return $last . ', ' . $firstMiddle;
            }
            if ($last) return $last;
            if ($firstMiddle) return $firstMiddle;
        }
        return $this->applicant ?? 'Applicant';
    }

    /**
     * Human-readable loan product name, resolved from the config matrix.
     */
    public function getLoanTypeLabelAttribute(): string
    {
        return config('loan_matrix.loan_types.' . $this->loan_type, $this->loan_type);
    }

    /**
     * Generate a month-by-month amortization schedule using the
     * cooperative's flat 12 % add-on rate.
     *
     * @return \Illuminate\Support\Collection
     */
    public function amortizationSchedule(): \Illuminate\Support\Collection
    {
        return app(AmortizationService::class)
            ->schedule((float) $this->amount, (int) ($this->term_months ?? 12));
    }

    public static function makeReference(): string
    {
        // Include soft-deleted rows so a withdrawn application's reference can
        // never be reissued. (max('id') alone adds WHERE deleted_at IS NULL.)
        $nextId = static::withTrashed()->max('id') + 1;

        return 'SDCC-' . now()->format('Y') . '-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Total paid — sum of all payments' amount_paid.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    /**
     * Outstanding balance = totalPayable - totalPaid.
     */
    public function outstandingBalance(): float
    {
        return max(0, $this->totalPayable() - $this->totalPaid());
    }

    /**
     * Next due date — first unpaid installment date or null if fully paid.
     */
    public function nextDueDate(): ?string
    {
        if ($this->outstandingBalance() <= 0) {
            return null;
        }
        $emi = $this->monthly_payment;
        $paid = $this->totalPaid();
        $paidInstallments = floor($paid / $emi);
        $disbursed = $this->disbursement_date ?? $this->reviewed_at ?? now();
        return \Carbon\Carbon::parse($disbursed)->addMonths($paidInstallments + 1)->format('Y-m-d');
    }

    /**
     * Human-readable status via LoanStatus.
     */
    public function statusString(): string
    {
        return \App\Support\LoanStatus::write($this->status, 'loan_applications');
    }
}