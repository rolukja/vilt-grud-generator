<?php

namespace Rolukja\ViltCrudGenerator\Providers;

use Illuminate\Support\ServiceProvider;

class ViltCrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/vilt-crud-generator.php' => config_path('vilt-crud-generator.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../Stubs' => base_path('stubs/vilt-crud-generator'),
        ], 'stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Rolukja\ViltCrudGenerator\Console\Commands\GenerateCrudCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/vilt-crud-generator.php',
            'vilt-crud-generator'
        );
    }
}