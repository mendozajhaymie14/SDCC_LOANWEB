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
     * @param  string  $action
     * @param  string  $details
     * @param  int|null  $userId
     * @return void
     */
    public static function log(string $action, string $details = '', ?int $userId = null): void
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

        self::create([
            'user_id'     => $userId ?? $authUser?->id,
            'admin_name' => $authUser?->name ?? 'System',
            'action'      => $action,
            'details'     => $details,
            'ip_address'  => $ip,
        ]);
    }
}