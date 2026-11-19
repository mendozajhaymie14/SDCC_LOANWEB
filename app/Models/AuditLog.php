<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'actor_id',
        'actor_role',
        'admin_name',
        'action',
        'details',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * Log an audit entry.
     *
     * Existing callers keep working: the old signature
     * log(action, details, userId) still writes a row with the actor's id
     * and name. New callers can pass the target and before/after values.
     *
     * @param  string  $action
     * @param  string  $details
     * @param  int|null  $userId  (deprecated) explicit actor id; the authenticated user is used otherwise
     * @param  array|null  $oldValues
     * @param  array|null  $newValues
     * @param  string|null  $targetType
     * @param  int|null  $targetId
     */
    public static function log(string $action, string $details = '', ?int $userId = null, ?array $oldValues = null, ?array $newValues = null, ?string $targetType = null, ?int $targetId = null): void
    {
        // Guard against console/CLI context where Auth and request() are unavailable.
        try {
            $authUser = Auth::user();
        } catch (\Throwable $e) {
            $authUser = null;
        }

        try {
            $ip = request()->ip();
        } catch (\Throwable $e) {
            $ip = '127.0.0.1';
        }

        $actorId   = $userId ?? $authUser?->id;
        $actorRole = $authUser ? Permissions::role($authUser) : null;

        self::create([
            'user_id'      => $actorId,
            'actor_id'     => $actorId,
            'actor_role'   => $actorRole,
            'admin_name'   => $authUser?->name ?? 'System',
            'action'       => $action,
            'details'      => $details,
            'target_type'  => $targetType,
            'target_id'    => $targetId,
            'old_values'   => $oldValues ? json_encode($oldValues) : null,
            'new_values'   => $newValues ? json_encode($newValues) : null,
            'ip_address'   => $ip,
        ]);
    }

    /**
     * Human-readable label for the record this entry acted on.
     *
     * The TARGET column answers "what was touched?" — e.g. "Payment #12" or
     * "Member #1441". target_type arrives as a short slug ('payment') or a
     * FQCN (App\Models\CoopMember); both render as a friendly noun.
     */
    public function targetLabel(): ?string
    {
        if (!$this->target_type) {
            return null;
        }

        $type = strtolower($this->target_type);
        $noun = match (true) {
            str_contains($type, 'payment')    => 'Payment',
            str_contains($type, 'member')     => 'Member',
            str_contains($type, 'application') => 'Application',
            str_contains($type, 'user')       => 'User',
            default => ucfirst(\Illuminate\Support\Str::afterLast($this->target_type, '\\')),
        };

        return $noun . ($this->target_id ? ' #' . $this->target_id : '');
    }
}