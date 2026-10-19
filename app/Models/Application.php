<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'applications';

    protected $fillable = [
        'loan_application_id',
        'app_id',
        'applicant',
        'loan_type',
        'amount',
        'term_months',
        'status',
        'ai_score',
        'ai_analysis_note',
        'has_risk_flags',
        'remaining_balance',
        'next_due_date',
        'disbursement_date',
        'recommended_amount',
        'risk_level',
        'risk_score',
        'risk_factors',
        'approved_amount',
        'admin_remarks',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'ai_score' => 'integer',
        'next_due_date' => 'date',
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /**
     * Get AI score. Returns the stored value as-is; null when no score yet.
     * No fabricated fallback — the UI shows N/A when there's no real score.
     */
    public function getAiScoreAttribute($value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    public function getAiScorePendingAttribute(): bool
    {
        return $this->attributes['ai_score'] === null;
    }
}