<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    // Explicitly define the table name in MySQL
    protected $table = 'applications';

    // Allow mass assignment for these fields
    protected $fillable = [
        'loan_application_id',
        'app_id',
        'applicant',
        'loan_type',
        'amount',
        'status',
        'ai_score',
        'ai_analysis_note',
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}