<?php

namespace App\Providers;

use App\Events\LoanApplicationApproved;
use App\Events\LoanApplicationDisbursed;
use App\Events\PaymentReceived;
use App\Listeners\NotifyMemberOfApproval;
use App\Listeners\NotifyMemberOfDisbursement;
use App\Listeners\NotifyMemberOfPayment;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

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

        // Loan application lifecycle events
        LoanApplicationApproved::class => [
            NotifyMemberOfApproval::class,
        ],

        LoanApplicationDisbursed::class => [
            NotifyMemberOfDisbursement::class,
        ],

        PaymentReceived::class => [
            NotifyMemberOfPayment::class,
        ],
    ];

    /**
     * Register any events / listeners.
     */
    public function boot(): void
    {
        Event::listen(
            LoanApplicationApproved::class,
            NotifyMemberOfApproval::class
        );

        Event::listen(
            LoanApplicationDisbursed::class,
            NotifyMemberOfDisbursement::class
        );

        Event::listen(
            PaymentReceived::class,
            NotifyMemberOfPayment::class
        );
    }
}