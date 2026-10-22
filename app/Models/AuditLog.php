<?php

namespace App\Models;

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
        'admin_name',
        'action',
        'details',
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
}