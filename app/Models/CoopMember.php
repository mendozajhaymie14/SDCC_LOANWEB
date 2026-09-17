<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoopMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'full_name',
        'date_of_birth',
        'email',
        'is_registered',
        'user_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_registered' => 'boolean',
    ];

    /**
     * Relationship to web User account once registered
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}