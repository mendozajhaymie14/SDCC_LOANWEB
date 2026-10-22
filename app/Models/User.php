<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

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
        'coop_member_id',
        'usertype',
        'role',
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
     * `role` and `usertype` are the same field stored twice: `role` is the
     * paper-facing name the permission matrix reads, `usertype` the legacy
     * one. They are kept in lockstep here, so no write path can leave a row
     * claiming two different roles — a stale `usertype` would otherwise
     * grant (or deny) the wrong permissions, because Permissions::role()
     * reads `role` first.
     *
     * `role` wins when the two disagree; a row that only carries the legacy
     * column inherits it.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            $role = trim((string) $user->role);

            if ($role === '') {
                $role = trim((string) $user->usertype) ?: Permissions::ROLE_USER;
            }

            $user->role = $role;
            $user->usertype = $role;
        });
    }

    /**
     * Staff accounts: the four roles an administrator may hand out.
     *
     * The second branch catches rows written before the `role` column
     * existed, which are still identified by their legacy `usertype`.
     */
    public function scopeStaff($query)
    {
        // The whole thing is one group, so a further where() on the query
        // (findOrFail($id), for instance) applies to both branches.
        return $query->where(function ($query) {
            $query->whereIn('role', Permissions::STAFF_ROLES)
                ->orWhere(function ($query) {
                    $query->whereNull('role')
                        ->whereIn('usertype', Permissions::STAFF_ROLES);
                });
        });
    }

    /**
     * Relationship to the cooperative member record.
     */
   public function coopMember()
{
    return $this->belongsTo(CoopMember::class, 'coop_member_id');
}

    /**
     * Relationship to the member application record.
     */
    public function memberApplication()
    {
        return $this->hasOne(MemberApplication::class);
    }

    /**
     * Does this user hold a live cooperative membership?
     *
     * A member is a user linked to a coop_members row whose status is the
     * canonical active value. A registered non-member ('user') has no such
     * link, so this is false for them.
     */
    public function isActiveCoopMember(): bool
    {
        return $this->coopMember?->isActiveMember() === true;
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
                if (count($parts) <= 2) return null;

                // Detect compound surname prefixes in what would be the "middle" position
                // e.g., "Juan Dela Cruz" -> parts = ["Juan", "Dela", "Cruz"]
                // "Dela" is a compound prefix, so it's actually part of the surname
                $compoundPrefixes = ['De', 'Dela', 'Del', 'Van', 'Von', 'Mc', 'Mac', 'Di', 'Da'];
                $middleStart = 1;

                // If there are 3+ parts and the second part is a compound prefix,
                // the middle starts from the third part (the rest of the surname)
                if (count($parts) >= 3 && in_array(mb_ucfirst($parts[1]), $compoundPrefixes, true)) {
                    $middleStart = 2;
                }

                return count($parts) > $middleStart
                    ? implode(' ', array_slice($parts, $middleStart, -1))
                    : null;
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
                if (count($parts) < 2) return end($parts) ?: '';

                // Detect compound surname prefixes
                // e.g., "Juan Dela Cruz" -> parts = ["Juan", "Dela", "Cruz"]
                // Should return "Dela Cruz" not just "Cruz"
                $compoundPrefixes = ['De', 'Dela', 'Del', 'Van', 'Von', 'Mc', 'Mac', 'Di', 'Da'];

                if (count($parts) >= 3 && in_array(mb_ucfirst($parts[1]), $compoundPrefixes, true)) {
                    // Compound surname: return last two parts joined
                    return $parts[1] . ' ' . end($parts);
                }

                return end($parts) ?: '';
            }
        );
    }

    /**
     * Accessor for the year the member joined the cooperative.
     *
     * Prefers the year encoded in the member_id (last 4 digits), falling
     * back to the coop member record's creation date, then the user's own.
     */
    public function memberSince(): Attribute
    {
        return Attribute::make(
            get: function () {
                $memberId = $this->coopMember?->member_id;
                if (is_string($memberId) && preg_match('/(\d{4})$/', $memberId, $matches)) {
                    $year = (int) $matches[1];
                    if ($year >= 1990 && $year <= 2030) {
                        return $matches[1];
                    }
                }

                return $this->coopMember?->created_at?->format('Y')
                    ?? $this->created_at?->format('Y')
                    ?? 'today';
            }
        );
    }
}