<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'usertype',
        'coop_member_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'first_name',
        'middle_name',
        'last_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship to the cooperative member record.
     */
   public function coopMember()
{
    return $this->belongsTo(CoopMember::class, 'coop_member_id');
}

    /**
     * Accessor for first_name derived from the single 'name' column.
     */
    public function firstName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = explode(' ', trim($this->name ?? ''));
                return $parts[0] ?? '';
            }
        );
    }

    /**
     * Accessor for middle_name derived from the single 'name' column.
     */
    public function middleName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = explode(' ', trim($this->name ?? ''));
                return count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null;
            }
        );
    }

    /**
     * Accessor for last_name derived from the single 'name' column.
     */
    public function lastName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = explode(' ', trim($this->name ?? ''));
                return count($parts) > 1 ? end($parts) : '';
            }
        );
    }
}