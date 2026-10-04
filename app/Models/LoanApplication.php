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
        'source_of_income', 'monthly_income', 'loan_type', 'amount', 'share_capital',
        'documents', 'term_months', 'status', 'admin_remarks', 'reviewed_at',
    ];

    protected $casts = [
        'birth_date'     => 'date',
        'reviewed_at'    => 'datetime',
        'documents'      => 'array',
        'amount'         => 'decimal:2',
        'share_capital'  => 'decimal:2',
        'monthly_income' => 'decimal:2',
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
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default    => 'Under review',
        };
    }

    /**
     * Human-readable loan product name, resolved from the config matrix.
     */
    public function getLoanTypeLabelAttribute(): string
    {
        return config('loan_matrix.loan_types.' . $this->loan_type, $this->loan_type);
    }

    public static function makeReference(): string
    {
        // Include soft-deleted rows so a withdrawn application's reference can
        // never be reissued. (max('id') alone adds WHERE deleted_at IS NULL.)
        $nextId = static::withTrashed()->max('id') + 1;

        return 'SDCC-' . now()->format('Y') . '-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}