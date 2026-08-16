<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Writeshh\Yarp\Tests\Fixtures\Models\Post;
use Writeshh\Yarp\Tests\Fixtures\Models\Tag;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\YarpServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [YarpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), function (Repository $config): void {
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        });
    }

    /**
     * Seed a user, defaulting every column so tests only state what they care about.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create(array_merge([
            'name' => 'User '.$sequence,
            'email' => 'user'.$sequence.'@example.test',
            'active' => true,
            'score' => 0,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makePost(User $user, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'user_id' => $user->id,
            'title' => 'Post title',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTag(array $attributes = []): Tag
    {
        return Tag::query()->create(array_merge(['label' => 'tag'], $attributes));
    }

    /**
     * The Testbench application's base path, used by generator tests.
     */
    protected function appBasePath(string $path = ''): string
    {
        /** @var Application $app */
        $app = $this->app;

        return $app->basePath($path);
    }
}
