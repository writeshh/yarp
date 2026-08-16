<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Services\RepositoryGenerator;

/**
 * v1 inserted new bindings at "the first `}` after the word register", which
 * broke the moment a register() body contained a closure, match arm or nested
 * block. These tests cover the replacement.
 */
class ProviderBindingTest extends GeneratorTestCase
{
    private function generator(): RepositoryGenerator
    {
        return $this->app->make(RepositoryGenerator::class);
    }

    private function writeProvider(string $registerBody): void
    {
        $path = $this->providerPath();

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, <<<PHP
        <?php

        namespace App\Providers;

        use Illuminate\Support\ServiceProvider;

        class RepositoryServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
        $registerBody
            }

            public function boot(): void
            {
                //
            }
        }
        PHP);
    }

    #[Test]
    public function it_appends_to_a_provider_whose_register_body_contains_a_closure(): void
    {
        $this->writeProvider(<<<'PHP'
                $this->app->singleton('something', function ($app) {
                    return new \stdClass;
                });
        PHP);

        $this->generator()->generate('User');

        $this->assertParses($this->providerPath());

        $contents = file_get_contents($this->providerPath());

        $this->assertStringContainsString('UserRepositoryInterface::class', $contents);
        $this->assertStringContainsString("singleton('something'", $contents);
    }

    #[Test]
    public function it_appends_to_a_provider_whose_register_body_contains_a_match(): void
    {
        $this->writeProvider(<<<'PHP'
                $driver = match (config('app.env')) {
                    'testing' => 'array',
                    default => 'redis',
                };
        PHP);

        $this->generator()->generate('User');

        $this->assertParses($this->providerPath());
        $this->assertStringContainsString('UserRepositoryInterface::class', file_get_contents($this->providerPath()));
    }

    #[Test]
    public function it_appends_to_a_provider_with_an_empty_register_body(): void
    {
        $this->writeProvider('        //');

        $this->generator()->generate('User');

        $this->assertParses($this->providerPath());
        $this->assertStringContainsString('UserRepositoryInterface::class', file_get_contents($this->providerPath()));
    }

    #[Test]
    public function it_uses_the_marker_when_the_provider_was_generated_by_yarp(): void
    {
        $this->generator()->generate('User');
        $this->generator()->generate('Post');
        $this->generator()->generate('Comment');

        $contents = file_get_contents($this->providerPath());

        $this->assertParses($this->providerPath());
        $this->assertSame(1, substr_count($contents, '// yarp:bindings'));

        foreach (['User', 'Post', 'Comment'] as $model) {
            $this->assertStringContainsString($model.'RepositoryInterface::class', $contents);
        }
    }

    #[Test]
    public function bindings_stay_in_generation_order(): void
    {
        $this->generator()->generate('User');
        $this->generator()->generate('Post');

        $contents = file_get_contents($this->providerPath());

        $this->assertLessThan(
            strpos($contents, 'PostRepositoryInterface'),
            strpos($contents, 'UserRepositoryInterface')
        );
    }

    #[Test]
    public function it_reports_a_note_when_no_register_method_can_be_found(): void
    {
        $path = $this->providerPath();
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, "<?php\n\n// not really a provider\n");

        $result = $this->generator()->generate('User');

        $this->assertNotEmpty($result->notes);
        $this->assertStringContainsString('by hand', implode(' ', $result->notes));
        $this->assertStringContainsString('$this->app->bind(', implode(' ', $result->notes));
    }

    #[Test]
    public function the_generated_binding_resolves_through_the_container(): void
    {
        $this->generator()->generate('User');

        $contents = file_get_contents($this->providerPath());

        // Extract the binding and assert both sides are what the container needs:
        // an abstract (the interface) distinct from the concrete implementation.
        $this->assertMatchesRegularExpression(
            '/\$this->app->bind\(\s*\\\\App\\\\Repositories\\\\Contracts\\\\UserRepositoryInterface::class,\s*\\\\App\\\\Repositories\\\\UserRepository::class\s*\);/',
            $contents
        );
    }
}
