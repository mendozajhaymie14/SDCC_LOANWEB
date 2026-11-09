<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\LoanStatus;

class CoopMember extends Model
{
    use HasFactory, SoftDeletes;

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
        'savings_balance',
        'is_migs',
        'address',
        'occupation',
        'monthly_income',
        'existing_monthly_obligations',
        'credit_limit_source',
        'credit_limit_computed_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_registered' => 'boolean',
        'status' => 'string',
        'credit_limit_source' => 'string',
        'credit_limit_computed_at' => 'datetime',
    ];

    /**
     * Relationship to web User account once registered
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Loan applications associated with this member
     */
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class, 'user_id');
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

    /**
     * Sum the outstanding balance of all approved loans for this member.
     *
     * Primary match: loans with user_id = this member's user_id.
     * Legacy fallback: loans with user_id = null AND email matching this
     * member's own non-blank email (case-insensitive). Staff-encoded loans
     * for members without a portal account are stored with user_id = null,
     * so this fallback is the only way to include them.
     *
     * LIMITATION: the email fallback is a legacy match — a future migration
     * should add loan_applications.coop_member_id to make the link explicit.
     */
    public static function outstandingBalance(CoopMember $member): float
    {
        $memberEmail = $member->email ? strtolower(trim($member->email)) : null;

        return (float) LoanApplication::where(function ($q) use ($member, $memberEmail) {
            $q->where('user_id', $member->user_id);
            if ($memberEmail) {
                $q->orWhere(function ($q2) use ($memberEmail) {
                    $q2->whereNull('user_id')
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$memberEmail]);
                });
            }
        })->where('status', LoanStatus::APPROVED)->sum('remaining_balance');
    }
}