<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Services\RepositoryGenerator;

/**
 * The generator turns its `name` argument into filesystem paths, so that argument
 * is the package's only real attack surface. These tests pin the behaviour that
 * keeps it contained.
 */
class GeneratorSecurityTest extends GeneratorTestCase
{
    private function generator(): RepositoryGenerator
    {
        return $this->app->make(RepositoryGenerator::class);
    }

    /**
     * Names that would escape the configured repositories directory.
     *
     * @return array<string, array{string}>
     */
    public static function traversalNames(): array
    {
        return [
            'parent directory' => ['../Evil'],
            'deep traversal' => ['../../../../tmp/Evil'],
            'windows traversal' => ['..\\..\\Evil'],
            'absolute path' => ['/tmp/Evil'],
            'nested path' => ['Sub/Evil'],
            'namespaced' => ['App\\Models\\Evil'],
            'null byte' => ["Evil\0"],
            'trailing extension' => ['Evil.php'],
        ];
    }

    #[Test]
    #[DataProvider('traversalNames')]
    public function it_refuses_names_that_would_escape_the_target_directory(string $name): void
    {
        $this->expectException(RepositoryException::class);

        $this->generator()->generate($name);
    }

    #[Test]
    #[DataProvider('traversalNames')]
    public function the_command_reports_a_traversal_attempt_as_a_failure(string $name): void
    {
        $this->artisan('make:repo', ['name' => [$name]])->assertFailed();
    }

    #[Test]
    public function a_traversal_attempt_writes_nothing_at_all(): void
    {
        $canary = dirname($this->workspace).'/yarp-canary-'.bin2hex(random_bytes(4)).'.php';

        try {
            $this->generator()->generate('../../'.basename($canary, '.php'));
        } catch (RepositoryException) {
            // expected
        }

        $this->assertFileDoesNotExist($canary);
        $this->assertFileDoesNotExist($this->workspace.'/app/Repositories');
    }

    #[Test]
    public function it_never_writes_outside_the_configured_directory(): void
    {
        $this->generator()->generate('User');

        foreach ([$this->repositoryPath(), $this->interfacePath(), $this->providerPath()] as $path) {
            $this->assertStringStartsWith($this->workspace, realpath($path) ?: $path);
        }
    }

    /**
     * v1 created app/Repositories with mode 0777, leaving it world-writable
     * wherever the deploying user's umask did not clamp it down.
     */
    #[Test]
    public function it_does_not_create_world_writable_directories(): void
    {
        // Windows has no POSIX permission bits: PHP ignores mkdir()'s mode
        // argument there and fileperms() always reports 0777 for a directory,
        // so this assertion is only meaningful on POSIX filesystems.
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permission bits do not exist on Windows.');
        }

        $this->generator()->generate('User');

        $directory = $this->workspace.'/app/Repositories';

        $this->assertDirectoryExists($directory);

        $mode = fileperms($directory) & 0777;

        $this->assertSame(0, $mode & 0o002, sprintf(
            'Directory [%s] is world-writable (mode %o).',
            $directory,
            $mode
        ));
    }

    #[Test]
    public function it_rejects_reserved_php_keywords(): void
    {
        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessageMatches('/reserved PHP keyword/');

        $this->generator()->generate('class');
    }

    #[Test]
    public function an_unreadable_custom_stub_path_falls_back_to_the_packaged_stubs(): void
    {
        config()->set('yarp.stub_path', $this->workspace.'/does-not-exist');

        $this->generator()->generate('User');

        $this->assertParses($this->repositoryPath());
    }
}
