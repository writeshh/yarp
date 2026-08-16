<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Support\ClassName;

/**
 * Writes repository classes, their interfaces and the binding service provider.
 *
 * Everything the generator puts on disk is derived from a name that has been
 * through {@see ClassName::normalise()} first, so no caller-supplied string ever
 * reaches a filesystem path unvalidated.
 */
class RepositoryGenerator
{
    public const TYPE_EXTENDED = 'extended';

    public const TYPE_STANDALONE = 'standalone';

    /**
     * Marker the generated provider carries so later bindings can be inserted
     * at a known point instead of by guessing at brace positions.
     */
    private const BINDING_MARKER = '// yarp:bindings';

    /**
     * Directory permissions for generated directories.
     *
     * v1 used 0777, which left app/Repositories world-writable on any host
     * where the deploying user's umask did not clamp it down.
     */
    private const DIRECTORY_MODE = 0755;

    public function __construct(
        protected Filesystem $files,
        protected Config $config,
    ) {}

    /**
     * Generate a repository (and optionally its interface), then register the binding.
     *
     * @throws RepositoryException
     */
    public function generate(
        string $name,
        ?string $type = null,
        ?bool $withInterface = null,
        bool $force = false,
    ): GeneratorResult {
        $model = ClassName::normalise($name);
        $type = $this->resolveType($type);
        $withInterface = $withInterface ?? (bool) $this->config->get('yarp.generate_interfaces', true);

        $result = new GeneratorResult;

        if ($withInterface) {
            $result = $result->with(...$this->writeInterface($model, $force));
        }

        $result = $result->with(...$this->writeRepository($model, $type, $withInterface, $force));

        return $result->with(...$this->registerBinding($model, $withInterface));
    }

    /*
    |--------------------------------------------------------------------------
    | File writers
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{created: array<int, string>, skipped: array<int, string>, updated: array<int, string>, notes: array<int, string>}
     */
    protected function writeInterface(string $model, bool $force): array
    {
        $path = $this->interfacePath($model);

        if ($this->files->exists($path) && ! $force) {
            return $this->outcome(skipped: [$path]);
        }

        $this->put($path, $this->render('Interface', [
            'namespace' => $this->interfaceNamespace(),
            'interface' => $this->interfaceClass($model),
            'model' => $model,
            'modelFqcn' => $this->modelFqcn($model),
        ]));

        return $this->outcome(created: [$path]);
    }

    /**
     * @return array{created: array<int, string>, skipped: array<int, string>, updated: array<int, string>, notes: array<int, string>}
     */
    protected function writeRepository(string $model, string $type, bool $withInterface, bool $force): array
    {
        $path = $this->repositoryPath($model);

        if ($this->files->exists($path) && ! $force) {
            return $this->outcome(skipped: [$path]);
        }

        $stub = $type === self::TYPE_STANDALONE ? 'Standalone' : 'Repository';

        $interfaceFqcn = $this->interfaceNamespace().'\\'.$this->interfaceClass($model);

        $this->put($path, $this->render($stub, [
            'namespace' => $this->repositoryNamespace(),
            'class' => $this->repositoryClass($model),
            'model' => $model,
            'modelFqcn' => $this->modelFqcn($model),
            'interface' => $withInterface ? $this->interfaceClass($model) : 'RepositoryInterface',
            'interfaceFqcn' => $withInterface ? $interfaceFqcn : RepositoryInterface::class,
        ]));

        return $this->outcome(created: [$path]);
    }

    /**
     * Create or extend the service provider that binds interfaces to repositories.
     *
     * @return array{created: array<int, string>, skipped: array<int, string>, updated: array<int, string>, notes: array<int, string>}
     */
    protected function registerBinding(string $model, bool $withInterface): array
    {
        $path = $this->providerPath();
        $binding = $this->bindingLine($model, $withInterface);

        if (! $this->files->exists($path)) {
            $this->put($path, $this->render('ServiceProvider', [
                'namespace' => (string) $this->config->get('yarp.provider.namespace', 'App\\Providers'),
                'class' => $this->providerClass(),
                'bindings' => $binding,
            ]));

            return $this->outcome(created: [$path], notes: [$this->registrationNote()]);
        }

        $contents = $this->files->get($path);

        // Already bound: nothing to do, and re-adding would duplicate the line.
        if (str_contains($contents, $this->repositoryClass($model).'::class')) {
            return $this->outcome();
        }

        $updated = $this->insertBinding($contents, $binding);

        if ($updated === null) {
            return $this->outcome(notes: [sprintf(
                'Could not find a register() method in [%s]. Add this binding by hand:%s%s',
                $path,
                PHP_EOL.PHP_EOL,
                trim($binding),
            )]);
        }

        $this->files->put($path, $updated);

        return $this->outcome(updated: [$path]);
    }

    /**
     * Insert a binding into an existing provider.
     *
     * Prefers the marker comment the package's own stub leaves behind. Falls back
     * to balanced brace matching on the register() method, which — unlike v1's
     * "first closing brace after the word register" — survives closures, match
     * expressions and nested blocks inside the method.
     */
    protected function insertBinding(string $contents, string $binding): ?string
    {
        if (str_contains($contents, self::BINDING_MARKER)) {
            return str_replace(
                self::BINDING_MARKER,
                trim($binding).PHP_EOL.PHP_EOL.'        '.self::BINDING_MARKER,
                $contents
            );
        }

        $closing = $this->locateRegisterBodyEnd($contents);

        if ($closing === null) {
            return null;
        }

        return substr($contents, 0, $closing).$binding.PHP_EOL.substr($contents, $closing);
    }

    /**
     * Find the offset of the closing brace of the register() method body.
     */
    protected function locateRegisterBodyEnd(string $contents): ?int
    {
        if (preg_match('/function\s+register\s*\([^)]*\)\s*(?::\s*\??[\w\\\\]+\s*)?\{/', $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $depth = 0;
        $length = strlen($contents);

        for ($i = $matches[0][1] + strlen($matches[0][0]) - 1; $i < $length; $i++) {
            $character = $contents[$i];

            if ($character === '{') {
                $depth++;

                continue;
            }

            if ($character !== '}') {
                continue;
            }

            if (--$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Stubs
    |--------------------------------------------------------------------------
    */

    /**
     * Render a stub, substituting `{{ placeholder }}` tokens.
     *
     * @param  array<string, string>  $replacements
     *
     * @throws RepositoryException
     */
    protected function render(string $stub, array $replacements): string
    {
        $contents = $this->stub($stub);

        foreach ($replacements as $key => $value) {
            $contents = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], $value, $contents);
        }

        return $contents;
    }

    /**
     * Locate a stub, preferring a published copy over the packaged one.
     *
     * @throws RepositoryException
     */
    protected function stub(string $stub): string
    {
        foreach ($this->stubPaths($stub) as $path) {
            if ($this->files->exists($path)) {
                return $this->files->get($path);
            }
        }

        throw RepositoryException::stubNotFound($stub);
    }

    /**
     * Candidate stub locations, most specific first.
     *
     * @return array<int, string>
     */
    protected function stubPaths(string $stub): array
    {
        $published = $this->config->get('yarp.stub_path');

        return array_values(array_filter([
            is_string($published) && $published !== '' ? rtrim($published, '/\\').DIRECTORY_SEPARATOR.$stub.'.stub' : null,
            $this->basePath('stubs/yarp/'.$stub.'.stub'),
            dirname(__DIR__).'/resources/stubs/'.$stub.'.stub',
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Naming
    |--------------------------------------------------------------------------
    */

    public function repositoryClass(string $model): string
    {
        return $model.(string) $this->config->get('yarp.repository.suffix', 'Repository');
    }

    public function interfaceClass(string $model): string
    {
        return $model.(string) $this->config->get('yarp.interface.suffix', 'RepositoryInterface');
    }

    public function repositoryNamespace(): string
    {
        return trim((string) $this->config->get('yarp.repository.namespace', 'App\\Repositories'), '\\');
    }

    public function interfaceNamespace(): string
    {
        return trim((string) $this->config->get('yarp.interface.namespace', 'App\\Repositories\\Contracts'), '\\');
    }

    public function modelFqcn(string $model): string
    {
        return trim((string) $this->config->get('yarp.model.namespace', 'App\\Models'), '\\').'\\'.$model;
    }

    public function providerClass(): string
    {
        return (string) $this->config->get('yarp.provider.class', 'RepositoryServiceProvider');
    }

    public function repositoryPath(string $model): string
    {
        return $this->directory('yarp.repository.path', 'app/Repositories')
            .DIRECTORY_SEPARATOR.$this->repositoryClass($model).'.php';
    }

    public function interfacePath(string $model): string
    {
        return $this->directory('yarp.interface.path', 'app/Repositories/Contracts')
            .DIRECTORY_SEPARATOR.$this->interfaceClass($model).'.php';
    }

    public function providerPath(): string
    {
        $configured = $this->config->get('yarp.provider.path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->basePath('app/Providers/'.$this->providerClass().'.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    protected function bindingLine(string $model, bool $withInterface): string
    {
        $repository = '\\'.$this->repositoryNamespace().'\\'.$this->repositoryClass($model);

        $abstract = $withInterface
            ? '\\'.$this->interfaceNamespace().'\\'.$this->interfaceClass($model)
            : $repository;

        return sprintf('        $this->app->bind(%s::class, %s::class);', $abstract, $repository);
    }

    protected function registrationNote(): string
    {
        $provider = trim((string) $this->config->get('yarp.provider.namespace', 'App\\Providers'), '\\')
            .'\\'.$this->providerClass();

        return sprintf(
            'Register %s::class in bootstrap/providers.php (Laravel 11+) or the providers array in config/app.php.',
            $provider
        );
    }

    /**
     * Resolve and validate the repository type.
     *
     * @throws RepositoryException
     */
    protected function resolveType(?string $type): string
    {
        $type = strtolower(trim($type ?? (string) $this->config->get('yarp.default_type', self::TYPE_EXTENDED)));

        // v1 spelled these "basic" and "extended"; both mapped to inheritance.
        $type = match ($type) {
            'base', 'basic', '' => self::TYPE_EXTENDED,
            default => $type,
        };

        if (! in_array($type, [self::TYPE_EXTENDED, self::TYPE_STANDALONE], true)) {
            throw new RepositoryException(sprintf(
                'Unknown repository type [%s]. Expected "%s" or "%s".',
                $type,
                self::TYPE_EXTENDED,
                self::TYPE_STANDALONE,
            ));
        }

        return $type;
    }

    protected function directory(string $key, string $default): string
    {
        $configured = $this->config->get($key);

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '/\\')
            : rtrim($this->basePath($default), '/\\');
    }

    /**
     * Resolve a path relative to the application root, without requiring the
     * `base_path()` helper to be available.
     */
    protected function basePath(string $path): string
    {
        $base = function_exists('base_path') ? base_path() : getcwd();

        return rtrim((string) $base, '/\\').DIRECTORY_SEPARATOR.ltrim($path, '/\\');
    }

    /**
     * Write a file, creating its directory if needed.
     *
     * @throws RepositoryException
     */
    protected function put(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->ensureDirectoryExists($directory, self::DIRECTORY_MODE, true);
        }

        if (! $this->files->isDirectory($directory) || ! $this->files->isWritable($directory)) {
            throw RepositoryException::directoryNotWritable($directory);
        }

        if ($this->files->put($path, $contents) === false) {
            throw RepositoryException::directoryNotWritable($directory);
        }
    }

    /**
     * @param  array<int, string>  $created
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $updated
     * @param  array<int, string>  $notes
     * @return array{created: array<int, string>, skipped: array<int, string>, updated: array<int, string>, notes: array<int, string>}
     */
    protected function outcome(array $created = [], array $skipped = [], array $updated = [], array $notes = []): array
    {
        return compact('created', 'skipped', 'updated', 'notes');
    }

    /**
     * Present a path relative to the project root, for console output.
     */
    public function relative(string $path): string
    {
        $base = $this->basePath('');

        return Str::startsWith($path, $base) ? Str::after($path, $base) : $path;
    }
}
