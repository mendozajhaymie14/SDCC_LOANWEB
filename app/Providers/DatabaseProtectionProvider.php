<?php

namespace App\Providers;

class DatabaseProtectionProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind our custom Kernel to the contract so both real CLI ($this->artisan) and
        // tests use it, overriding handle()/call() with the guard logic.
        $this->app->bind(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );
    }
}