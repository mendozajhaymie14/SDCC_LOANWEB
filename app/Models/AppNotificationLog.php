<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotificationLog extends Model
{
    use HasFactory;

    protected $table = 'app_notification_logs';

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
     * @param  string  $email
     * @param  string  $subject
     * @param  string  $type
     * @param  string  $status
     * @param  string  $channel
     * @param  int|null  $userId
     * @param  int|null  $loanApplicationId
     * @return static
     */
    public static function log(string $email, string $subject, string $type = 'system', string $status = 'Sent', string $channel = 'email', ?int $userId = null, ?int $loanApplicationId = null): static
    {
        return static::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'notification_type' => $type,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => $status === 'Sent' ? now() : null,
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