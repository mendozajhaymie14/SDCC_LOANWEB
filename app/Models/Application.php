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
        'status',
        'ai_score',
        'ai_analysis_note',
        'has_risk_flags',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'ai_score' => 'integer',
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /**
     * Get AI score with fallback for pending applications.
     */
    public function getAiScoreAttribute($value)
    {
        if ($value !== null) {
            return $value;
        }

        if ($this->relationLoaded('loanApplication') && $this->loanApplication) {
            $loanApp = $this->loanApplication;
            if ($loanApp->monthly_income > 0 && $loanApp->amount > 0) {
                $ratio = $loanApp->amount / $loanApp->monthly_income;
                return max(1, min(100, (int) round(50 - $ratio * 10)));
            }
        }

        return null;
    }

    public function getAiScorePendingAttribute(): bool
    {
        return $this->attributes['ai_score'] === null;
    }
}