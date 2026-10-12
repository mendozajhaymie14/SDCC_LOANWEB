<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppNotification extends Model
{
    use SoftDeletes;

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'loan_application_id',
        'title',
        'message',
        'type',
        'is_read',
        'read_at',
        'sent_at',
        'data',
    ];

    protected $casts = [
        'read_at'  => 'datetime',
        'sent_at'  => 'datetime',
        'is_read'  => 'boolean',
        'data'     => 'array',
    ];

    /**
     * Notification types used by the reminder system.
     */
    public const TYPE_DUE_SOON    = 'due_soon';
    public const TYPE_DUE_TODAY   = 'due_today';
    public const TYPE_OVERDUE     = 'overdue';
    public const TYPE_PAYMENT_REC = 'payment_received';

    /**
     * Notification types used by the old approval/rejection flow.
     * Kept for backward compatibility.
     */
    public const TYPE_APPROVED = 'approved';
    public const TYPE_REJECTED = 'rejected';

    public static function getTypes(): array
    {
        return [
            self::TYPE_APPROVED,
            self::TYPE_REJECTED,
            self::TYPE_DUE_SOON,
            self::TYPE_DUE_TODAY,
            self::TYPE_OVERDUE,
            self::TYPE_PAYMENT_REC,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Scope: only unread notifications for a given user.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('is_read');
    }

    /**
     * Scope: payment reminder notifications only.
     */
    public function scopeReminders($query)
    {
        return $query->whereIn('type', [
            self::TYPE_DUE_SOON,
            self::TYPE_DUE_TODAY,
            self::TYPE_OVERDUE,
            self::TYPE_PAYMENT_REC,
        ]);
    }

    /**
     * Mark this notification as read.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
