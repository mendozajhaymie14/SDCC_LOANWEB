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
     * The admin who encoded the payment, if any.
     */
    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}
