<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Reminder Service
    |--------------------------------------------------------------------------
    |
    | Cron batch window for payment reminders. All times are compared against
    | Asia/Manila business days, so this value is in days and applies to the
    | "due soon" stage (today excluded).
    |
    */

    'due_soon_window_days' => 3,
];
