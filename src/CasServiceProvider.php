<?php

namespace Soeverse\Cas;

use Illuminate\Support\ServiceProvider;

class CasServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Gabungkan default konfigurasi package dengan konfigurasi user (jika ada)
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cas.php',
            'cas'
        );

        // Binding CasServiceInterface ke CasService
        $this->app->singleton(CasServiceInterface::class, function ($app) {
            return new CasService();
        });

        // Binding opsional untuk alias Facade
        $this->app->alias(CasServiceInterface::class, 'cas');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mendaftarkan file konfigurasi untuk di-publish oleh user
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cas.php' => config_path('cas.php'),
            ], 'cas-config');
        }
    }
}
