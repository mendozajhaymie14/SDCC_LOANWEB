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
        'first_name',
        'middle_name',
        'house_no',
        'street',
        'barangay',
        'municipality',
        'zip_code',
        'stay_years',
        'stay_months',
        'perm_house_no',
        'perm_street',
        'perm_barangay',
        'perm_municipality',
        'perm_zip_code',
        'perm_stay_years',
        'perm_stay_months',
        'residency_type',
        'contact_number',
        'email',
        'birthdate',
        'nationality',
        'place_of_birth',
        'gender',
        'occupation',
        'civil_status',
        'tin',
        'id_picture',
        'proof_of_billing',
        'character_references',
        'status',
        'reviewed_at',
        'reviewed_by',
        'approved_member_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'reviewed_at' => 'datetime',
            'stay_years' => 'int',
            'stay_months' => 'int',
            'perm_stay_years' => 'int',
            'perm_stay_months' => 'int',
        'character_references' => 'array',
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
}