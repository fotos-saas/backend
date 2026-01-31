<?php

namespace App\Providers;

use App\Events\AlbumCreated;
use App\Events\OrderPaymentReceived;
use App\Events\OrderPlaced;
use App\Events\OrderShipped;
use App\Events\OrderStatusChanged;
use App\Events\PasswordChanged;
use App\Events\PhotoUploaded;
use App\Events\TabloUserRegistered;
use App\Events\TabloWorkflowCompleted;
use App\Events\UserCreatedWithCredentials;
use App\Events\UserRegistered;
use App\Events\WorkSessionCreated;
use App\Listeners\SendAlbumCreatedEmail;
use App\Listeners\SendOrderPaymentReceivedEmail;
use App\Listeners\SendOrderPlacedEmail;
use App\Listeners\SendOrderShippedEmail;
use App\Listeners\SendOrderStatusChangedEmail;
use App\Listeners\SendPasswordChangedEmail;
use App\Listeners\SendPhotoUploadedEmail;
use App\Listeners\SendTabloCompletedEmail;
use App\Listeners\SendTabloUserRegisteredEmail;
use App\Listeners\SendUserCreatedCredentialsEmail;
use App\Listeners\SendUserRegisteredEmail;
use App\Listeners\SendWorkSessionAccessCodeEmail;
use App\Listeners\ApplyWatermarkToPreview;
use App\Listeners\LogLoginAttempt;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserRegistered::class => [
            SendUserRegisteredEmail::class,
        ],
        UserCreatedWithCredentials::class => [
            SendUserCreatedCredentialsEmail::class,
        ],
        AlbumCreated::class => [
            SendAlbumCreatedEmail::class,
        ],
        OrderPlaced::class => [
            SendOrderPlacedEmail::class,
        ],
        OrderStatusChanged::class => [
            SendOrderStatusChangedEmail::class,
        ],
        OrderPaymentReceived::class => [
            SendOrderPaymentReceivedEmail::class,
        ],
        OrderShipped::class => [
            SendOrderShippedEmail::class,
        ],
        PhotoUploaded::class => [
            SendPhotoUploadedEmail::class,
        ],
        WorkSessionCreated::class => [
            SendWorkSessionAccessCodeEmail::class,
        ],
        PasswordChanged::class => [
            SendPasswordChangedEmail::class,
        ],
        TabloUserRegistered::class => [
            SendTabloUserRegisteredEmail::class,
        ],
        TabloWorkflowCompleted::class => [
            SendTabloCompletedEmail::class,
        ],
        ConversionHasBeenCompletedEvent::class => [
            ApplyWatermarkToPreview::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        LogLoginAttempt::class,
    ];
}
