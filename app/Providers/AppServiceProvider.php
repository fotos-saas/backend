<?php

namespace App\Providers;

use App\Models\Photo;
use App\Models\TabloMissingPerson;
use App\Models\TabloNotification;
use App\Models\WorkSession;
use App\Observers\PhotoObserver;
use App\Observers\TabloMissingPersonObserver;
use App\Observers\TabloNotificationObserver;
use App\Observers\WorkSessionObserver;
use App\Services\BrandingService;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Photo::observe(PhotoObserver::class);
        WorkSession::observe(WorkSessionObserver::class);
        TabloMissingPerson::observe(TabloMissingPersonObserver::class);
        TabloNotification::observe(TabloNotificationObserver::class);

        $branding = $this->app->make(BrandingService::class);

        if ($branding->getEmail()) {
            Mail::alwaysFrom($branding->getEmail(), $branding->getName());
        }

        // Rate limiting for image conversion (allow frequent status polling)
        RateLimiter::for('image-conversion', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Rate limiting for tablo login (brute force protection: 20 attempts / 15 min / IP)
        RateLimiter::for('tablo-login', function (Request $request) {
            return Limit::perMinutes(15, 20)->by($request->ip());
        });

        // Rate limiting for newsfeed post creation (10/hour per session)
        RateLimiter::for('newsfeed-post', function (Request $request) {
            return Limit::perHour(10)->by($request->header('X-Guest-Session', $request->ip()));
        });

        // Rate limiting for newsfeed comments (5/minute per session)
        RateLimiter::for('newsfeed-comment', function (Request $request) {
            return Limit::perMinute(5)->by($request->header('X-Guest-Session', $request->ip()));
        });

        // Rate limiting for newsfeed likes (30/minute per session)
        RateLimiter::for('newsfeed-like', function (Request $request) {
            return Limit::perMinute(30)->by($request->header('X-Guest-Session', $request->ip()));
        });

        // Rate limiting for registration (5/hour per IP)
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // Rate limiting for password reset (3/hour per IP+email)
        RateLimiter::for('password-reset', function (Request $request) {
            $email = $request->input('email', 'unknown');
            return Limit::perHour(3)->by($request->ip() . '|' . $email);
        });

        // Rate limiting for 2FA verification (5/minute per IP+email)
        RateLimiter::for('2fa-verify', function (Request $request) {
            $email = $request->input('email', 'unknown');
            return Limit::perMinute(5)->by($request->ip() . '|' . $email);
        });

        // Rate limiting for QR registration (10/minute per IP)
        RateLimiter::for('qr-registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Rate limiting for email verification resend (3/minute per user)
        RateLimiter::for('verification-resend', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(3)->by($user?->id ?? $request->ip());
        });

        // Filament Table globális konfiguráció - 200 is opció legyen
        Table::configureUsing(function (Table $table): void {
            $table->paginationPageOptions([10, 25, 50, 100, 200]);
        });
    }
}
