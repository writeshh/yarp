<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Writeshh\Yarp\Tests\TestCase;

/**
 * Points every generator path at a throwaway directory, so tests exercise real
 * filesystem writes without touching the Testbench skeleton.
 */
abstract class GeneratorTestCase extends TestCase
{
    protected string $workspace;

    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->workspace = sys_get_temp_dir().'/yarp-'.bin2hex(random_bytes(8));

        $this->files->ensureDirectoryExists($this->workspace);

        // macOS resolves /var to /private/var, so compare against the real path
        // or every assertStringStartsWith on a generated path fails spuriously.
        $this->workspace = realpath($this->workspace) ?: $this->workspace;

        config()->set('yarp.repository.path', $this->workspace.'/app/Repositories');
        config()->set('yarp.interface.path', $this->workspace.'/app/Repositories/Contracts');
        config()->set('yarp.provider.path', $this->workspace.'/app/Providers/RepositoryServiceProvider.php');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    protected function repositoryPath(string $model = 'User'): string
    {
        return $this->workspace.'/app/Repositories/'.$model.'Repository.php';
    }

    protected function interfacePath(string $model = 'User'): string
    {
        return $this->workspace.'/app/Repositories/Contracts/'.$model.'RepositoryInterface.php';
    }

    protected function providerPath(): string
    {
        return $this->workspace.'/app/Providers/RepositoryServiceProvider.php';
    }

    /**
     * Assert that generated PHP actually parses.
     */
    protected function assertParses(string $path): void
    {
        $this->assertFileExists($path);

        $output = [];
        $status = 0;
        exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($path)), $output, $status);

        $this->assertSame(0, $status, sprintf(
            "Generated file [%s] is not valid PHP:\n%s",
            $path,
            implode(PHP_EOL, $output)
        ));
    }
}
