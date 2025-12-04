<?php

namespace App\Providers;

use App\Events\PaymentSuccessful;
use App\Events\DeductionProcessed;
use App\Events\EmployeePasswordChanged;
use App\Events\UserAccountDeleted;
use App\Events\LoanApproved;
use App\Events\LoanRejected;
use App\Events\GrantApproved;
use App\Events\GrantRejected;
use App\Listeners\SendPaymentSuccessNotification;
use App\Listeners\SendDeductionNotification;
use App\Listeners\SendPasswordChangedNotification;
use App\Listeners\SendAccountDeletionNotification;
use App\Listeners\NotifyApplicantLoanApproved;
use App\Listeners\NotifyApplicantLoanRejected;
use App\Listeners\NotifyApplicantGrantApproved;
use App\Listeners\NotifyApplicantGrantRejected;
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
        DeductionProcessed::class => [
            SendDeductionNotification::class,
        ],
        EmployeePasswordChanged::class => [
            SendPasswordChangedNotification::class,
        ],
        UserAccountDeleted::class => [
            SendAccountDeletionNotification::class,
        ],
        LoanApproved::class => [
            NotifyApplicantLoanApproved::class,
        ],
        LoanRejected::class => [
            NotifyApplicantLoanRejected::class,
        ],
        GrantApproved::class => [
            NotifyApplicantGrantApproved::class,
        ],
        GrantRejected::class => [
            NotifyApplicantGrantRejected::class,
        ],
        // You can add other existing events here
        \App\Events\StkPushRequested::class => [
            \App\Listeners\ProcessStkPush::class,
        ],
        \App\Events\MiniStatementSent::class => [
            \App\Listeners\HandleMiniStatement::class,
        ],
        \App\Events\SupportMessageSubmitted::class => [
            \App\Listeners\HandleSupportMessageSubmission::class,
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
