<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<int, class-string, array<int, class-string>>
     */
    protected $listen = [
        // Authentication events
        Registered::class => [
            // Add listeners for new user registration if needed
        ],
        Login::class => [
            // Add listeners for user login if needed
        ],
        Logout::class => [
            // Add listeners for user logout if needed
        ],
    ];

    /**
     * Register any events / listeners.
     */
    public function boot(): void
    {
        //
    }
}