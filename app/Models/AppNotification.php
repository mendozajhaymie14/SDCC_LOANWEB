<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppNotification extends Model
{
    use SoftDeletes;
    protected $table = 'app_notifications';

    protected $fillable = [
        'app_id',
        'applicant',
        'type',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
