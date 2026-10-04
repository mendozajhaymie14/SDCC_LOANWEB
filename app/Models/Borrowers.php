<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrowers extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'borrowers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'borrower_id',
        'full_name',
        'email',
        'phone_number',
        'address',
        'monthly_income',
        'ai_credit_score',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'monthly_income' => 'decimal:2',
        'ai_credit_score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: A borrower can have many loan applications.
     */
    public function applications()
    {
        return $this->hasMany(Application::class, 'applicant', 'full_name');
    }
}