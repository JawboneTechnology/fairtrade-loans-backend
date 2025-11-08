<?php

namespace App\Providers;

use App\Events\PaymentSuccessful;
use App\Listeners\SendPaymentSuccessNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PaymentSuccessful::class => [
            SendPaymentSuccessNotification::class,
        ],
        // You can add other existing events here
        \App\Events\StkPushRequested::class => [
            \App\Listeners\ProcessStkPush::class,
        ],
        \App\Events\MiniStatementSent::class => [
            \App\Listeners\HandleMiniStatement::class,
        ],
        // Add other existing event-listener mappings as needed
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
