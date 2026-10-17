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
        'status',
        'sent_at',
        'error_message',
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
     * @return static
     */
    public static function log(string $email, string $subject, string $type = 'system', string $status = 'Sent'): static
    {
        return static::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'notification_type' => $type,
            'status' => $status,
            'sent_at' => $status === 'Sent' ? now() : null,
        ]);
    }

    /**
     * Log a failed email notification.
     *
     * @param  string  $email
     * @param  string  $subject
     * @param  string  $type
     * @param  string  $error
     * @return static
     */
    public static function logFailed(string $email, string $subject, string $type = 'system', string $error = ''): static
    {
        return static::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'notification_type' => $type,
            'status' => 'Failed',
            'error_message' => $error,
        ]);
    }
}