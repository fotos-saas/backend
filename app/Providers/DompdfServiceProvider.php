<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Egyedi DomPDF ServiceProvider.
 *
 * A barryvdh/laravel-dompdf auto-discovery-t letiltottuk a composer.json-ban,
 * mert console környezetben hibát okoz. Ez a provider manuálisan regisztrálja
 * a dompdf-et, de CSAK ha nem console környezetben vagyunk.
 */
class DompdfServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Console környezetben NE regisztráljuk a dompdf-et
        // mert a package:discover és egyéb artisan parancsok hibásak lesznek
        if ($this->app->runningInConsole()) {
            return;
        }

        // HTTP kérés esetén regisztráljuk az eredeti ServiceProvider-t
        $this->app->register(\Barryvdh\DomPDF\ServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nincs szükség boot logikára
    }
}
