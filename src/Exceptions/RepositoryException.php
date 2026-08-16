<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Exceptions;

use RuntimeException;

class RepositoryException extends RuntimeException
{
    /**
     * The model does not use the SoftDeletes trait, so trashed records
     * cannot be queried or restored.
     */
    public static function softDeletesNotSupported(string $model, string $method): self
    {
        return new self(sprintf(
            'Cannot call %s() because [%s] does not use the [Illuminate\Database\Eloquent\SoftDeletes] trait.',
            $method,
            $model
        ));
    }

    /**
     * A mass update or delete was attempted with no constraints, which would
     * have rewritten or emptied the entire table.
     */
    public static function unconstrainedMassOperation(string $method, string $repository): self
    {
        return new self(sprintf(
            '%s::%s() was called without any constraints, which would affect every row in the table. '
            .'Add a constraint (for example ->where(...)) first, or use ->query()->%s(...) to bypass this guard deliberately.',
            $repository,
            $method,
            $method === 'deleteWhere' ? 'delete' : 'update'
        ));
    }

    /**
     * The generator was handed something that is not a usable PHP class name.
     */
    public static function invalidClassName(string $name): self
    {
        return new self(sprintf(
            'The name [%s] is not a valid PHP class name. Use a StudlyCase name such as "User" or "BlogPost".',
            $name
        ));
    }

    /**
     * The generator was handed a name that is a reserved PHP keyword.
     */
    public static function reservedClassName(string $name): self
    {
        return new self(sprintf(
            'The name [%s] is a reserved PHP keyword and cannot be used as a class name.',
            $name
        ));
    }

    /**
     * A directory required by the generator could not be created.
     */
    public static function directoryNotWritable(string $path): self
    {
        return new self(sprintf('The directory [%s] could not be created or is not writable.', $path));
    }

    /**
     * The generator refused to clobber an existing file.
     */
    public static function fileAlreadyExists(string $path): self
    {
        return new self(sprintf('The file [%s] already exists. Pass --force to overwrite it.', $path));
    }

    /**
     * A stub file could not be located, in the published path or the package.
     */
    public static function stubNotFound(string $stub): self
    {
        return new self(sprintf('The stub [%s] could not be found.', $stub));
    }
}
