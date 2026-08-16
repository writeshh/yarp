<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MakeRepositoryCommandTest extends GeneratorTestCase
{
    #[Test]
    public function it_generates_a_repository_an_interface_and_a_provider(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $this->assertParses($this->repositoryPath());
        $this->assertParses($this->interfacePath());
        $this->assertParses($this->providerPath());
    }

    #[Test]
    public function the_generated_repository_extends_the_base_class(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $contents = file_get_contents($this->repositoryPath());

        $this->assertStringContainsString('namespace App\Repositories;', $contents);
        $this->assertStringContainsString('use Writeshh\Yarp\BaseRepository;', $contents);
        $this->assertStringContainsString('class UserRepository extends BaseRepository implements UserRepositoryInterface', $contents);
        $this->assertStringContainsString('public function __construct(User $model)', $contents);
    }

    #[Test]
    public function the_standalone_type_uses_the_trait_instead_of_inheritance(): void
    {
        $this->artisan('make:repo', ['name' => ['User'], '--type' => 'standalone'])->assertSuccessful();

        $contents = file_get_contents($this->repositoryPath());

        $this->assertStringContainsString('use Writeshh\Yarp\Concerns\InteractsWithRepository;', $contents);
        $this->assertStringContainsString('class UserRepository implements UserRepositoryInterface', $contents);
        $this->assertStringNotContainsString('extends BaseRepository', $contents);
    }

    /**
     * v1 accepted --type=basic and --type=extended; both meant inheritance.
     */
    #[Test]
    #[DataProvider('legacyTypes')]
    public function it_still_accepts_the_v1_type_names(string $type): void
    {
        $this->artisan('make:repo', ['name' => ['User'], '--type' => $type])->assertSuccessful();

        $this->assertStringContainsString('extends BaseRepository', file_get_contents($this->repositoryPath()));
    }

    /**
     * @return array<int, array{string}>
     */
    public static function legacyTypes(): array
    {
        return [['basic'], ['base'], ['extended']];
    }

    #[Test]
    public function it_rejects_an_unknown_type(): void
    {
        $this->artisan('make:repo', ['name' => ['User'], '--type' => 'nonsense'])->assertFailed();

        $this->assertFileDoesNotExist($this->repositoryPath());
    }

    #[Test]
    public function it_binds_the_interface_to_the_implementation(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $provider = file_get_contents($this->providerPath());

        $this->assertStringContainsString(
            '$this->app->bind(\App\Repositories\Contracts\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);',
            $provider
        );
    }

    /**
     * v1 emitted `bind(UserRepository::class, UserRepository::class)`, which is a
     * no-op: the container already resolves a concrete class to itself.
     */
    #[Test]
    public function it_does_not_emit_a_self_referential_binding(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $this->assertStringNotContainsString(
            'bind(\App\Repositories\UserRepository::class, \App\Repositories\UserRepository::class)',
            file_get_contents($this->providerPath())
        );
    }

    #[Test]
    public function it_binds_the_concrete_class_when_interfaces_are_disabled(): void
    {
        $this->artisan('make:repo', ['name' => ['User'], '--no-interface' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($this->interfacePath());

        $contents = file_get_contents($this->repositoryPath());
        $this->assertStringContainsString('use Writeshh\Yarp\Contracts\RepositoryInterface;', $contents);
        $this->assertStringContainsString('implements RepositoryInterface', $contents);
    }

    #[Test]
    public function the_interface_flag_overrides_the_config_default(): void
    {
        config()->set('yarp.generate_interfaces', false);

        $this->artisan('make:repo', ['name' => ['User'], '--interface' => true])->assertSuccessful();

        $this->assertParses($this->interfacePath());
    }

    #[Test]
    public function it_generates_several_repositories_at_once(): void
    {
        $this->artisan('make:repo', ['name' => ['User', 'Post', 'Comment']])->assertSuccessful();

        foreach (['User', 'Post', 'Comment'] as $model) {
            $this->assertParses($this->repositoryPath($model));
        }

        $this->assertParses($this->providerPath());
    }

    #[Test]
    public function it_appends_bindings_to_an_existing_provider(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();
        $this->artisan('make:repo', ['name' => ['Post']])->assertSuccessful();

        $provider = file_get_contents($this->providerPath());

        $this->assertStringContainsString('UserRepositoryInterface::class', $provider);
        $this->assertStringContainsString('PostRepositoryInterface::class', $provider);
        $this->assertParses($this->providerPath());
    }

    #[Test]
    public function it_does_not_duplicate_an_existing_binding(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();
        $this->artisan('make:repo', ['name' => ['User'], '--force' => true])->assertSuccessful();

        $provider = file_get_contents($this->providerPath());

        $this->assertSame(1, substr_count($provider, 'UserRepositoryInterface::class'));
    }

    #[Test]
    public function it_skips_existing_files_without_force(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();
        file_put_contents($this->repositoryPath(), '<?php // hand-written');

        $this->artisan('make:repo', ['name' => ['User']])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        $this->assertStringContainsString('hand-written', file_get_contents($this->repositoryPath()));
    }

    #[Test]
    public function it_overwrites_existing_files_with_force(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();
        file_put_contents($this->repositoryPath(), '<?php // hand-written');

        $this->artisan('make:repo', ['name' => ['User'], '--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('hand-written', file_get_contents($this->repositoryPath()));
    }

    #[Test]
    public function it_strips_a_redundant_repository_suffix(): void
    {
        $this->artisan('make:repo', ['name' => ['UserRepository']])->assertSuccessful();

        $this->assertFileExists($this->repositoryPath('User'));
        $this->assertFileDoesNotExist($this->repositoryPath('UserRepository'));
    }

    #[Test]
    public function it_studly_cases_the_given_name(): void
    {
        $this->artisan('make:repo', ['name' => ['blog_post']])->assertSuccessful();

        $this->assertFileExists($this->repositoryPath('BlogPost'));
        $this->assertStringContainsString('use App\Models\BlogPost;', file_get_contents($this->repositoryPath('BlogPost')));
    }

    #[Test]
    public function it_honours_configured_namespaces(): void
    {
        config()->set('yarp.repository.namespace', 'Domain\\Data');
        config()->set('yarp.interface.namespace', 'Domain\\Data\\Contracts');
        config()->set('yarp.model.namespace', 'Domain\\Entities');

        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $contents = file_get_contents($this->repositoryPath());

        $this->assertStringContainsString('namespace Domain\Data;', $contents);
        $this->assertStringContainsString('use Domain\Entities\User;', $contents);
        $this->assertStringContainsString('use Domain\Data\Contracts\UserRepositoryInterface;', $contents);
    }

    #[Test]
    public function it_honours_a_configured_suffix(): void
    {
        config()->set('yarp.repository.suffix', 'Repo');

        $this->artisan('make:repo', ['name' => ['User']])->assertSuccessful();

        $this->assertFileExists($this->workspace.'/app/Repositories/UserRepo.php');
    }

    #[Test]
    public function it_reports_the_provider_registration_step_once(): void
    {
        $this->artisan('make:repo', ['name' => ['User']])
            ->expectsOutputToContain('bootstrap/providers.php')
            ->assertSuccessful();
    }
}
