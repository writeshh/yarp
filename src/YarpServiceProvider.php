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
        $this->loadViewsFrom(__DIR__ . '/resources/stubs', 'RepositoryPattern');

        $this->publishes([
            __DIR__ . '/resources/stubs' => resource_path('vendor/writeshh/stubs'),
        ]);
    }
}
