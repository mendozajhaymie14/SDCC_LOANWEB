<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoopMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'full_name',
        'date_of_birth',
        'email',
        'is_registered',
        'status',
        'user_id',
        'approved_credit_limit',
        'share_capital',
        'savings_balance',
        'is_migs',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_registered' => 'boolean',
        'status' => 'string',
    ];

    /**
     * Relationship to web User account once registered
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}