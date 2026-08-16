<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Commands\MakeRepositoryCommand;
use Writeshh\Yarp\Facades\Yarp;
use Writeshh\Yarp\Services\RepositoryGenerator;
use Writeshh\Yarp\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_package_config(): void
    {
        $this->assertSame('extended', config('yarp.default_type'));
        $this->assertTrue(config('yarp.generate_interfaces'));
        $this->assertSame(15, config('yarp.pagination.per_page'));
    }

    #[Test]
    public function application_config_overrides_the_package_default(): void
    {
        config()->set('yarp.default_type', 'standalone');

        $this->assertSame('standalone', config('yarp.default_type'));
    }

    #[Test]
    public function it_registers_the_generator_as_a_singleton(): void
    {
        $this->assertInstanceOf(RepositoryGenerator::class, $this->app->make(RepositoryGenerator::class));
        $this->assertSame(
            $this->app->make(RepositoryGenerator::class),
            $this->app->make(RepositoryGenerator::class)
        );
    }

    #[Test]
    public function it_registers_the_make_repo_command(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('make:repo', $commands);
        $this->assertInstanceOf(MakeRepositoryCommand::class, $commands['make:repo']);
    }

    #[Test]
    public function the_facade_resolves_the_generator(): void
    {
        $this->assertSame('UserRepository', Yarp::repositoryClass('User'));
        $this->assertSame('UserRepositoryInterface', Yarp::interfaceClass('User'));
        $this->assertSame('App\Models\User', Yarp::modelFqcn('User'));
    }

    #[Test]
    public function it_publishes_config_and_stubs(): void
    {
        $groups = ServiceProvider::publishableGroups();

        $this->assertContains('yarp-config', $groups);
        $this->assertContains('yarp-stubs', $groups);
    }

    #[Test]
    public function every_packaged_stub_is_publishable(): void
    {
        $stubs = glob(__DIR__.'/../../src/resources/stubs/*.stub');

        $this->assertNotEmpty($stubs);

        foreach (['Repository', 'Standalone', 'Interface', 'ServiceProvider'] as $expected) {
            $this->assertContains(
                $expected.'.stub',
                array_map('basename', $stubs),
                sprintf('The [%s] stub is missing from the package.', $expected)
            );
        }
    }
}
