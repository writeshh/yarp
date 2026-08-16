<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Facades;

use Illuminate\Support\Facades\Facade;
use Writeshh\Yarp\Services\RepositoryGenerator;

/**
 * Facade over the repository generator, for generating repositories outside of
 * the console — from a package installer, a scaffolding command of your own, or
 * a test.
 *
 * @method static \Writeshh\Yarp\Services\GeneratorResult generate(string $name, ?string $type = null, ?bool $withInterface = null, bool $force = false)
 * @method static string repositoryClass(string $model)
 * @method static string interfaceClass(string $model)
 * @method static string repositoryNamespace()
 * @method static string interfaceNamespace()
 * @method static string modelFqcn(string $model)
 * @method static string providerClass()
 * @method static string repositoryPath(string $model)
 * @method static string interfacePath(string $model)
 * @method static string providerPath()
 * @method static string relative(string $path)
 *
 * @see RepositoryGenerator
 */
class Yarp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RepositoryGenerator::class;
    }
}
