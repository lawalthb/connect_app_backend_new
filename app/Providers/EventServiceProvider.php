<?php

namespace App\Providers;

use App\Events\AdCreatedEvent;
use App\Events\AdApprovedEvent;
use App\Events\AdRejectedEvent;
use App\Listeners\SendAdReviewNotification;
use App\Listeners\SendAdApprovalNotification;
use App\Listeners\SendAdRejectionNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        AdCreatedEvent::class => [
            SendAdReviewNotification::class,
        ],
        AdApprovedEvent::class => [
            SendAdApprovalNotification::class,
        ],
        AdRejectedEvent::class => [
            SendAdRejectionNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
