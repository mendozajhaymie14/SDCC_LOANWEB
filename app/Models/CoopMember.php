<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoopMember extends Model
{
    use HasFactory;

    /**
     * The one canonical member status.
     *
     * The column used to mix 'active', 'Active', 'Approved' and 'approved'.
     * Every query and view now uses these constants, and the data migration
     * folds the old spellings into STATUS_ACTIVE.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    /** The statuses a member record may hold. */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PENDING,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];

    protected $fillable = [
        'member_id',
        'full_name',
        'date_of_birth',
        'email',
        'contact_number',
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

    /**
     * Split the stored full name into the three register-form fields.
     *
     * coop_members keeps a single full_name column, so the parts are derived
     * the same way everywhere: first word, last word, whatever is between.
     *
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    public function nameParts(): array
    {
        $parts = preg_split('/\s+/', trim($this->full_name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
        }

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => ''];
        }

        $first = array_shift($parts);
        $last  = array_pop($parts);

        return [
            'first_name'  => $first,
            'middle_name' => implode(' ', $parts),
            'last_name'   => $last,
        ];
    }

    /**
     * Is this member linked to an active cooperative membership?
     *
     * A loan may only be taken by somebody whose cooperative membership is
     * live, so this is the single check the loan gate uses.
     */
    public function isActiveMember(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}