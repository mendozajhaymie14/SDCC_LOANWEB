<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberApplication extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'surname',
        'last_name',
        'first_name',
        'middle_name',
        'house_no',
        'street',
        'barangay',
        'municipality',
        'province',
        'zip_code',
        'date_of_birth',
        'monthly_income',
        'existing_monthly_obligations',
        'source_of_income',
        'savings_balance',
        'stay_years',
        'stay_months',
        'perm_street',
        'perm_barangay',
        'perm_municipality',
        'perm_province',       // <-- ADDED
        'perm_zip_code',
        'perm_stay_years',
        'perm_stay_months',
        'residency_type',
        'contact_number',
        'email',
        'nationality',
        'place_of_birth',
        'occupation',
        'status',
        'reviewed_at',
        'reviewed_by',
        'approved_member_id',
        'user_id',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'stay_years' => 'int',
            'stay_months' => 'int',
            'perm_stay_years' => 'int',
            'perm_stay_months' => 'int',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Accessor for the full name, derived from the individual parts.
     */
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = array_filter([
                    $this->surname,
                    $this->first_name,
                    $this->middle_name,
                ]);

                return implode(' ', $parts);
            }
        );
    }

    /**
     * Accessor for a human-readable status label.
     */
    public function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match ($this->status) {
                    'pending'  => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    default    => $this->status,
                };
            }
        );
    }

    /**
     * The approved CoopMember linked to this application, if any.
     */
    public function approvedMember()
    {
        return $this->belongsTo(CoopMember::class, 'approved_member_id', 'id');
    }
}