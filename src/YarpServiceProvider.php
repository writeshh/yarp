<?php

declare(strict_types=1);

namespace Writeshh\Yarp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Writeshh\Yarp\Commands\MakeRepositoryCommand;
use Writeshh\Yarp\Services\RepositoryGenerator;

class YarpServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/yarp.php', 'yarp');

        $this->app->singleton(RepositoryGenerator::class, fn ($app) => new RepositoryGenerator(
            $app->make(Filesystem::class),
            $app->make(Config::class),
        ));
    }

    /**
     * Bootstrap console-only concerns.
     *
     * Publishing and command registration are guarded by runningInConsole(), so
     * a web request never pays for them.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeRepositoryCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/yarp.php' => $this->configPath('yarp.php'),
        ], 'yarp-config');

        $this->publishes([
            __DIR__.'/resources/stubs' => $this->basePath('stubs/yarp'),
        ], 'yarp-stubs');
    }

    private function configPath(string $path): string
    {
        return function_exists('config_path')
            ? config_path($path)
            : $this->basePath('config/'.$path);
    }

    private function basePath(string $path): string
    {
        return $this->app->basePath($path);
    }
}
