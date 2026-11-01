<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    protected $table = 'settings';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::created(static function (self $setting) {
            static::logAudit('created', $setting->key, $setting->value);
        });

        static::updated(static function (self $setting, $original) {
            static::logAudit('updated', $setting->key, $setting->value, $original['value'] ?? null);
        });

        static::deleted(static function (self $setting) {
            static::logAudit('deleted', $setting->key, $setting->value);
        });
    }

    protected static function logAudit(string $action, string $key, $newValue, $oldValue = null): void
    {
        try {
            $user = Auth::id();
            $changedBy = $user ? "User #{$user}" : 'system';
            $details = [
                'key' => $key,
                'old' => $oldValue,
                'new' => $newValue,
            ];
            \App\Models\AuditLog::log('Setting ' . $action, "{$changedBy}: {$key}");
        } catch (\Throwable $e) {
            // Audit log failure should not break settings
        }
    }

    public static function get(string $key, $default = null)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return $default;
        }
        return Cache::remember("setting:{$key}", 300, fn () => static::where('key', $key)->value('value')) ?? $default;
    }

    public static function set(string $key, $value): bool
    {
        $record = static::firstOrNew(['key' => $key]);
        $record->value = $value;
        $saved = $record->save();
        Cache::forget("setting:{$key}");
        return $saved;
    }

    public static function render(string $key, string $template, array $values = []): string
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return $template;
        }
        $value = static::get($key, '');
        if ($value === null || $value === '') {
            return $template;
        }
        // Allow placeholders in format {name} (e.g. {member_name}, {amount}, {due_date})
        foreach ($values as $k => $v) {
            $value = str_replace('{' . strtolower($k) . '}', $v, $value);
        }
        return is_string($value) ? $value : $template;
    }
}