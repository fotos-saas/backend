<?php

namespace App\Providers;

use App\Services\CompreFaceService;
use App\Services\Contracts\FaceRecognitionServiceInterface;
use Illuminate\Support\ServiceProvider;

class FaceRecognitionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(FaceRecognitionServiceInterface::class, CompreFaceService::class);
    }

    /**
     * Bootstrap services.
     */
    public function bootstrap(): void
    {
        //
    }
}
