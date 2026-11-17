<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Support\LoanStatus;
use App\Services\CreditLimitService;
use Illuminate\Support\Facades\DB;

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
        'approved_monthly_credit_limit',
        'savings_balance',
        'is_migs',
        'address',
        'occupation',
        'monthly_income',
        'existing_monthly_obligations',
        'credit_limit_source',
        'credit_limit_computed_at',
        'monthly_credit_limit_source',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_registered' => 'boolean',
        'status' => 'string',
        'credit_limit_source' => 'string',
        'credit_limit_computed_at' => 'datetime',
        'monthly_credit_limit_source' => 'string',
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
     * Has this member had their credit limit assessed (not null)?
     *
     * Returns true when approved_credit_limit is set (including 0, which
     * means assessed but with zero capacity). Returns false when null,
     * meaning the member has no assessed limit.
     */
    public function hasAssessedCreditLimit(): bool
    {
        return $this->approved_credit_limit !== null;
    }

    /**
     * The member's approved credit limit, as a float.
     *
     * Returns null when the limit has not been assessed (null in DB).
     * Returns 0 when assessed but with no capacity.
     * Returns the numeric value when assessed with a positive limit.
     */
    public function creditLimit(): ?float
    {
        return $this->approved_credit_limit !== null ? (float) $this->approved_credit_limit : null;
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
            if ($member->user_id) {
                $q->where('user_id', $member->user_id);
                if ($memberEmail) {
                    $q->orWhere(function ($q2) use ($memberEmail) {
                        $q2->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(email)) = ?', [$memberEmail]);
                    });
                }
            } else {
                // Portal-less member: identified by email only. A bare
                // `user_id IS NULL` clause sweeps in every other
                // portal-less member's loans.
                $q->whereNull('user_id')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$memberEmail ?? '']);
            }
        })->where('status', LoanStatus::APPROVED)->sum('remaining_balance');
    }

    /**
     * Sum the outstanding principal of all approved loans for this member.
     *
     * Same user_id + email matching logic as outstandingBalance().
     * Only counts approved loans.
     */
    public static function outstandingPrincipal(CoopMember $member): float
    {
        $memberEmail = $member->email ? strtolower(trim($member->email)) : null;

        $loans = LoanApplication::where(function ($q) use ($member, $memberEmail) {
            if ($member->user_id) {
                $q->where('user_id', $member->user_id);
                if ($memberEmail) {
                    $q->orWhere(function ($q2) use ($memberEmail) {
                        $q2->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(email)) = ?', [$memberEmail]);
                    });
                }
            } else {
                // Portal-less member: identified by email only. A bare
                // `user_id IS NULL` clause sweeps in every other
                // portal-less member's loans.
                $q->whereNull('user_id')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$memberEmail ?? '']);
            }
        })->where('status', LoanStatus::APPROVED)->get();

        return (float) $loans->sum(fn ($loan) => $loan->outstandingPrincipal());
    }

    /**
     * Available credit = approved credit limit minus outstanding principal.
     *
     * Floored at 0 (no negative available credit). Returns null when the
     * credit limit has not been assessed (null in DB). Returns 0 when
     * assessed but fully consumed.
     */
    public function availableCredit(): ?float
    {
        if (!$this->hasAssessedCreditLimit()) {
            return null;
        }

        $limit = (float) $this->approved_credit_limit;
        $principalOutstanding = self::outstandingPrincipal($this);

        return max(0, $limit - $principalOutstanding);
    }

    /**
     * Monthly repayment capacity per member (separate from total credit limit).
     * Returns the STORED approved_monthly_credit_limit when set (auto or manual),
     * otherwise falls back to suggestedMonthlyLimit().
     */
    public function monthlyCapacity(): ?float
    {
        if ($this->approved_monthly_credit_limit !== null) {
            return (float) $this->approved_monthly_credit_limit;
        }

        return $this->suggestedMonthlyLimit();
    }

    /**
     * The system-suggested monthly credit limit: max(0, income * dti_limit - obligations).
     * Never reads the stored value — always recomputed from current financials.
     * Returns null when monthly_income is null, never 0 for a null income.
     */
    public function suggestedMonthlyLimit(): ?float
    {
        if ($this->monthly_income === null) {
            return null;
        }

        $income = (float) $this->monthly_income;
        $obligations = (float) ($this->existing_monthly_obligations ?? 0);

        return app(\App\Services\CreditLimitService::class)
            ->calculateMonthlyCapacity($income, $obligations);
    }

    /**
     * Monthly used: sum of the AMOUNT (principal) of this member's loans
     * APPROVED during the CURRENT calendar month (Asia/Manila). Resets on
     * the 1st. Pending/rejected never count; paid-off status is irrelevant.
     *
     * Scoping: portal members use user_id (with an email fallback for legacy
     * staff-encoded loans where user_id is null). Portal-less members
     * (user_id is null) are scoped by email ONLY, so two portal-less members
     * can never count each other's loans. A member with neither user_id nor
     * email scopes to zero loans.
     */
    public function monthlyUsed(): float
    {
        if (!$this->user_id && !$this->email) {
            return 0.0;
        }

        $now = Carbon::now('Asia/Manila');
        $start = $now->copy()->startOfMonth()->setTimezone('UTC');
        $end   = $now->copy()->endOfMonth()->setTimezone('UTC');

        $query = LoanApplication::where('status', LoanStatus::APPROVED)
            ->whereNotNull('reviewed_at')
            ->whereBetween('reviewed_at', [$start, $end])
            ->where(function ($q) {
                // group: user_id OR email fallback, always scoped by status=approved
                if ($this->user_id) {
                    $q->where('user_id', $this->user_id);
                    if ($this->email) {
                        $q->orWhere(fn ($q2) => $q2->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]));
                    }
                } elseif ($this->email) {
                    $q->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]);
                }
            });

        return (float) $query->sum(DB::raw('COALESCE(approved_amount, amount)'));
    }

    /**
     * Sum of the ORIGINAL monthly payment (loan accessor; never
     * re-amortized) of this member's approved, not-yet-paid loans.
     *
     * This is the old EMI-based "monthly used" figure, kept ONLY for the
     * DTI ratio in LoanEvaluationService — it is NOT the monthly allowance
     * consumed by approvals (see monthlyUsed()).
     *
     * Scoping: same user_id + email fallback as monthlyUsed().
     */
    public function monthlyPaymentsActive(): float
    {
        $query = LoanApplication::where('status', LoanStatus::APPROVED)->where(function ($q) {
            // group: user_id OR email fallback, always scoped by status=approved
            if ($this->user_id) {
                $q->where('user_id', $this->user_id);
                if ($this->email) {
                    $q->orWhere(fn ($q2) => $q2->whereNull('user_id')
                        ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]));
                }
            } elseif ($this->email) {
                $q->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]);
            }
        });

        $used = 0.0;

        foreach ($query->get() as $loan) {
            $monthlyPayment = (float) $loan->monthly_payment;
            if ($monthlyPayment > 0) {
                $used += $monthlyPayment;
            }
        }

        return $used;
    }

    /**
     * OTHER pending loans: [count, sum of AMOUNT (principal)]. Pending loans
     * are not yet in monthlyUsed() — this is what approving them would add.
     * Same user_id/email scoping as monthlyUsed(); excludes $exceptLoanId
     * so an application's own pending loan does not warn about itself.
     *
     * @return array{0: int, 1: float}
     */
    public function otherPendingLoans(?int $exceptLoanId = null): array
    {
        $query = LoanApplication::where('status', LoanStatus::PENDING)->where(function ($q) {
            // group: user_id OR email fallback, always scoped by status=pending
            if ($this->user_id) {
                $q->where('user_id', $this->user_id);
                if ($this->email) {
                    $q->orWhere(fn ($q2) => $q2->whereNull('user_id')
                        ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]));
                }
            } elseif ($this->email) {
                $q->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]);
            }
        });

        if ($exceptLoanId) {
            $query->where('id', '!=', $exceptLoanId);
        }

        $loans = $query->get();

        return [$loans->count(), $loans->sum(fn ($l) => (float) $l->amount)];
    }

    /**
     * The member's loan application still under review (pending or flagged),
     * or null when none. Newest first, so the reference shown to staff is the
     * most recent undecided request.
     *
     * Scoped exactly like monthlyUsed(): portal members by user_id with an
     * email fallback for legacy staff-encoded loans (user_id null), portal-less
     * members by email only. A member with neither user_id nor email never
     * matches, and two portal-less members never block each other.
     */
    public function pendingLoanApplication(): ?LoanApplication
    {
        if (!$this->user_id && !$this->email) {
            return null;
        }

        return LoanApplication::whereIn('status', [LoanStatus::PENDING, LoanStatus::FLAGGED])
            ->where(function ($q) {
                if ($this->user_id) {
                    $q->where('user_id', $this->user_id);
                    if ($this->email) {
                        $q->orWhere(fn ($q2) => $q2->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]));
                    }
                } elseif ($this->email) {
                    $q->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))]);
                }
            })
            ->latest()
            ->first();
    }

    /**
     * Does the member already have a loan request under review?
     *
     * One loan request at a time: pending OR flagged both count as under
     * review. Approve or reject the open one before submitting another.
     */
    public function hasPendingLoan(): bool
    {
        return $this->pendingLoanApplication() !== null;
    }

    /**
     * Monthly available = max(0, monthly_capacity - monthly_used).
     * Returns null when capacity is null; never returns a negative number.
     */
    public function monthlyAvailable(): ?float
    {
        $capacity = $this->monthlyCapacity();
        if ($capacity === null) {
            return null;
        }

        return max(0, $capacity - $this->monthlyUsed());
    }

    /**
     * Max loan amount over 12 months at the current monthly limit
     * (monthly limit × 12). Null when the limit has never been assessed.
     */
    public function maxForTwelveMonths(): ?float
    {
        $capacity = $this->monthlyCapacity();

        return $capacity !== null ? $capacity * 12 : null;
    }

    /**
     * Resolve the CoopMember for a loan application.
     *
     * Order: users.coop_member_id relation → coop_members.user_id → email fallback.
     * Returns null when no member can be resolved.
     */
    public static function forLoanApplication(LoanApplication $loanApp): ?self
    {
        if ($loanApp->user_id) {
            $user = User::withTrashed()->find($loanApp->user_id);
            if ($user && $user->coop_member_id) {
                $member = self::withTrashed()->find($user->coop_member_id);
                if ($member) {
                    return $member;
                }
            }
            $member = self::withTrashed()->where('user_id', $loanApp->user_id)->first();
            if ($member) {
                return $member;
            }
        }

        if ($loanApp->email) {
            return self::withTrashed()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($loanApp->email))])
                ->first();
        }

        return null;
    }
}