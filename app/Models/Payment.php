<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'loan_application_id',
        'user_id',
        'amount_paid',
        'remaining_balance_after',
        'reference_no',
        'payment_date',
        'encoded_by',
        'notes',
        'payment_method',
    ];

    protected $casts = [
        'amount_paid'               => 'decimal:2',
        'remaining_balance_after'   => 'decimal:2',
        'payment_date'              => 'date',
    ];

    /**
     * The loan this payment was applied against.
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * The member who made the payment (denormalised from the loan).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The borrower's full name, resolved from the loan application's user
     * (formatted "Last, First Middle"), falling back to the loan's own
     * full_name column, then to N/A.
     */
    public function borrowerName(): string
    {
        $loan = $this->loanApplication;
        if ($loan) {
            return $loan->fullName() ?? $loan->full_name ?? 'N/A';
        }
        return 'N/A';
    }

    /**
     * Borrower-facing loan application reference, prefixed with #.
     */
    public function borrowerRef(): string
    {
        $loan = $this->loanApplication;
        if ($loan) {
            return '#' . ($loan->app_id ?? $loan->id ?? 'N/A');
        }
        return '#N/A';
    }

    /**
     * Display reference: stored reference_no when present, otherwise #REP-<id>.
     */
    public function refLabel(): string
    {
        return $this->reference_no ? $this->reference_no : '#REP-' . $this->id;
    }

    /**
     * The Application (admin-side) row for this payment's loan, if any.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'loan_application_id', 'loan_application_id');
    }

    /**
     * Borrower-facing app ID: from the Application row's app_id, or fallback.
     */
    public function appId(): string
    {
        if ($this->application?->app_id) {
            return $this->application->app_id;
        }
        return $this->loanApplication?->reference ?? $this->loan_application_id ?? 'N/A';
    }
}
