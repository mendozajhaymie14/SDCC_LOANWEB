<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

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
        'reference_number',
        'recorded_by',
        'notes',
        'recommended_amount',
        'risk_level',
        'risk_score',
        'risk_factors',
        'evaluation_reasons',
        'document_check',
        'approved_amount',
        'admin_remarks',
        'approved_by',
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

    /**
     * Total paid — delegates to related LoanApplication.
     */
    public function totalPaid(): float
    {
        return $this->loanApplication ? $this->loanApplication->totalPaid() : 0.0;
    }

    /**
     * Outstanding balance = totalPayable - totalPaid.
     */
    public function outstandingBalance(): float
    {
        return max(0, $this->totalPayable() - $this->totalPaid());
    }

    /**
     * Total payable — delegates to related LoanApplication.
     */
    public function totalPayable(): float
    {
        return $this->loanApplication ? $this->loanApplication->totalPayable() : 0.0;
    }

    /**
     * The staff user who approved this application.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Next due date — delegates to related LoanApplication.
     */
    public function nextDueDate(): ?string
    {
        return $this->loanApplication ? $this->loanApplication->nextDueDate() : null;
    }

    /**
     * Human-readable status via LoanStatus.
     */
    public function statusString(): string
    {
        return \App\Support\LoanStatus::write($this->status, 'applications');
    }

    /**
     * Plain-language evaluation reasons.
     *
     * evaluation_reasons holds the readable list; risk_factors now stores the
     * numeric details map, which yields nothing readable. Falls back through
     * the linked LoanApplication, then to legacy risk_factors strings. Safe
     * when the evaluation_reasons column does not exist yet: Eloquent returns
     * null for attributes absent from the row, so the chain falls through.
     */
    public function getReasonsAttribute(): array
    {
        // evaluation_reasons arrives with its migration; on a database that
        // has not run it yet, skip the column read instead of relying on
        // Eloquent's null-for-missing-attribute behaviour.
        $stored = Schema::hasColumn('applications', 'evaluation_reasons')
            ? $this->evaluation_reasons
            : null;

        foreach ([$stored, $this->loanApplication?->evaluation_reasons] as $value) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded)) {
                $strings = array_values(array_filter($decoded, fn ($r) => is_string($r) && $r !== ''));
                if ($strings) {
                    return $strings;
                }
            }
        }

        $factors = is_string($this->risk_factors) ? json_decode($this->risk_factors, true) : $this->risk_factors;
        if (!is_array($factors)) {
            return [];
        }

        return collect($factors)->map(function ($item) {
            if (is_array($item)) {
                return $item['reason'] ?? $item['message'] ?? null;
            }
            return is_string($item) ? $item : null;
        })->filter()->values()->all();
    }
}