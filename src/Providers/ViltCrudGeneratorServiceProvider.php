<?php

namespace ViltCrudGenerator\Providers;

use Illuminate\Support\ServiceProvider;

class ViltCrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Konfigurationsdatei veröffentlichen
        $this->publishes([
            __DIR__ . '/../../config/vilt-crud-generator.php' => config_path('vilt-crud-generator.php'),
        ], 'config');

        // Stubs veröffentlichen
        $this->publishes([
            __DIR__ . '/../Stubs' => base_path('stubs/vilt-crud-generator'),
        ], 'stubs');

        // Registriere Artisan-Befehle
        if ($this->app->runningInConsole()) {
            $this->commands([
                \ViltCrudGenerator\Console\Commands\GenerateCrudCommand::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register()
    {
        // Konfigurationsdatei laden
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/vilt-crud-generator.php',
            'vilt-crud-generator'
        );
    }
}
