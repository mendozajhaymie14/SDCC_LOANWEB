<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotificationLog extends Model
{
    use HasFactory;

    protected $table = 'app_notification_logs';

    /**
     * Human-readable labels for notification types (avoids ucfirst() on snake_case keys).
     */
    public const TYPE_LABELS = [
        'approved'             => 'Loan Approved',
        'rejected'             => 'Loan Rejected',
        'due_soon'             => 'Due Soon',
        'due_today'            => 'Due Today',
        'overdue'              => 'Overdue',
        'payment_received'     => 'Payment Received',
        'membership_approved'  => 'Membership Approved',
        'membership_rejected'  => 'Membership Rejected',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst($type);
    }

    /**
     * Colour bucket for a notification type pill.
     */
    public static function typeColor(string $type): string
    {
        return match (strtolower(trim($type))) {
            'approved', 'payment_received', 'membership_approved' => 'ok',
            'rejected', 'membership_rejected'                     => 'danger',
            'due_soon', 'due_today', 'overdue'                    => 'warn',
            default                                               => 'neutral',
        };
    }

    protected $fillable = [
        'recipient_email',
        'subject',
        'notification_type',
        'channel',
        'status',
        'sent_at',
        'error_message',
        'user_id',
        'loan_application_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * The user the notification is for.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The loan application the notification is for.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Scope for a specific status.
     */
    public function scopeWhereStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for notification type.
     */
    public function scopeWhereType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Get the status color class.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Sent' => 'green',
            'Failed' => 'red',
            default => 'gray',
        };
    }

    /**
     * Log an email notification.
     *
     * @param  string|null  $email  Null when no recipient could be resolved (Skipped rows).
     * @param  string  $subject
     * @param  string  $type
     * @param  string  $status
     * @param  string  $channel
     * @param  int|null  $userId
     * @param  int|null  $loanApplicationId
     * @param  string|null  $reason  Human-readable reason for Skipped/Failed rows.
     * @return static
     */
    public static function log(?string $email, string $subject, string $type = 'system', string $status = 'Sent', string $channel = 'email', ?int $userId = null, ?int $loanApplicationId = null, ?string $reason = null): static
    {
        return static::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'notification_type' => $type,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => $status === 'Sent' ? now() : null,
            'error_message' => $reason,
            'user_id' => $userId,
            'loan_application_id' => $loanApplicationId,
        ]);
    }

    /**
     * Log a failed email notification.
     *
     * @param  string  $email
     * @param  string  $subject
     * @param  string  $type
     * @param  string  $error
     * @param  string  $channel
     * @param  int|null  $userId
     * @param  int|null  $loanApplicationId
     * @return static
     */
    public static function logFailed(string $email, string $subject, string $type = 'system', string $error = '', string $channel = 'email', ?int $userId = null, ?int $loanApplicationId = null): static
    {
        return static::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'notification_type' => $type,
            'channel' => $channel,
            'status' => 'Failed',
            'error_message' => $error,
            'user_id' => $userId,
            'loan_application_id' => $loanApplicationId,
        ]);
    }
}