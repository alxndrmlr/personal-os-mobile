<?php

namespace App\Providers;

use Ikromjon\LocalNotifications\LocalNotificationsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\MicrophoneServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider;
use Native\Mobile\UI\Theme;
use NativePHP\MediaPlayer\MediaPlayerServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
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
        $this->app->booted(fn () => Theme::pushToNative());
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            MicrophoneServiceProvider::class,
            LocalNotificationsServiceProvider::class,
            NativeUIServiceProvider::class,
            MediaPlayerServiceProvider::class,
        ];
    }
}
