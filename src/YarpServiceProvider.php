<?php

namespace Writeshh\Yarp;

use Illuminate\Support\ServiceProvider;
use Writeshh\Yarp\Commands\RepositoryPattern;

class YarpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->commands([
            RepositoryPattern::class,
        ]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Load the stub views
        $this->loadViewsFrom(__DIR__ . '/resources/stubs', 'RepositoryPattern');

        // Allow publishing stubs
        $this->publishes([
            __DIR__ . '/resources/stubs' => resource_path('vendor/writeshh/stubs'),
        ], 'yarp-stubs');

        // Register the service singleton
        $this->app->singleton('RepositoryPattern', function () {
            return new \Writeshh\Yarp\Service\RepositoryService();
        });
    }
}
