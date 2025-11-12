<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AppInitializationProvider extends ServiceProvider
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
        // Only run in specific environments or conditions
        if ($this->shouldClearConfig()) {
            try {
                Artisan::call('config:clear');
                Log::info('Configuration cache cleared during application initialization');
            } catch (\Exception $e) {
                Log::warning('Failed to clear config cache during initialization: ' . $e->getMessage());
            }
        }
    }

    /**
     * Determine if config should be cleared
     */
    private function shouldClearConfig(): bool
    {
        // Only clear config in development or when a flag is set
        return config('app.env') === 'local' || 
               config('app.debug') === true ||
               env('FORCE_CONFIG_CLEAR_ON_BOOT', false);
    }
}
