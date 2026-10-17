<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'next_due_date'     => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rough monthly amortisation used for the preview on the form and the
     * dashboard. Flat 12% per annum add-on rate — change RATE if the
     * cooperative uses a different one.
     */
    public const RATE = 0.12;

    public function getMonthlyPaymentAttribute(): float
    {
        $years    = $this->term_months / 12;
        $interest = (float) $this->amount * self::RATE * $years;

        return round(((float) $this->amount + $interest) / $this->term_months, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved'   => 'Approved',
            'disbursed'  => 'Disbursed & Released',
            'rejected'   => 'Rejected',
            default      => 'Under review',
        };
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
        $principal = (float) $this->amount;
        $termMonths = (int) ($this->term_months ?? 12);
        $rate = config('loan_matrix.rate', 0.12);
        $monthlyPayment = $this->getMonthlyPaymentAttribute();

        $schedule = [];
        $remaining = $principal;

        for ($month = 1; $month <= $termMonths; $month++) {
            $interest = round($remaining * ($rate / 12), 2);
            $principalPortion = round($monthlyPayment - $interest, 2);
            $principalPortion = max(0, min($principalPortion, $remaining));
            $remaining = max(0, round($remaining - $principalPortion, 2));

            $schedule[] = [
                'month'               => $month,
                'due_date'            => now()->addMonths($month - 1)->endOfMonth(),
                'payment'             => $monthlyPayment,
                'principal'           => $principalPortion,
                'interest'            => $interest,
                'remaining_balance'   => $remaining,
            ];
        }

        return collect($schedule);
    }

    public static function makeReference(): string
    {
        // Include soft-deleted rows so a withdrawn application's reference can
        // never be reissued. (max('id') alone adds WHERE deleted_at IS NULL.)
        $nextId = static::withTrashed()->max('id') + 1;

        return 'SDCC-' . now()->format('Y') . '-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}