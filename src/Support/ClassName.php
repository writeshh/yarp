<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Support;

use Illuminate\Support\Str;
use Writeshh\Yarp\Exceptions\RepositoryException;

/**
 * Validation and normalisation for user-supplied class names.
 *
 * The generator writes files to paths derived from the `make:repo` argument, so
 * that argument must be proven to be a bare PHP class name before it is ever
 * concatenated into a path. Without this check, a name like `../../../routes/web`
 * escapes the repositories directory and overwrites arbitrary files.
 */
final class ClassName
{
    /**
     * Bare PHP class name: a letter or underscore, then letters, digits or underscores.
     *
     * Deliberately excludes backslashes, so namespaced input is rejected rather
     * than silently reinterpreted, and excludes dots and slashes, which is what
     * closes the path traversal.
     */
    private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Words PHP will not accept as a class name.
     *
     * @var array<int, string>
     */
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else',
        'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float',
        'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable',
        'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'numeric', 'object',
        'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait',
        'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    /**
     * Normalise a raw `make:repo` argument into a StudlyCase model class name.
     *
     * Accepts `user`, `User`, `blog_post` and `UserRepository`, all of which
     * normalise to the model name the stubs expect.
     *
     * @throws RepositoryException when the input is not a usable class name
     */
    public static function normalise(string $name): string
    {
        // Deliberately not PHP's default trim charlist, which includes "\0":
        // trimming a trailing null byte would quietly turn "User\0" into a
        // valid name instead of rejecting a string that has no business here.
        $name = trim($name, " \t\n\r\x0B\f");

        if ($name === '' || preg_match(self::PATTERN, $name) !== 1) {
            throw RepositoryException::invalidClassName($name);
        }

        $name = Str::studly($name);

        // `make:repo UserRepository` should not produce UserRepositoryRepository.
        if (Str::endsWith($name, 'Repository') && $name !== 'Repository') {
            $name = Str::beforeLast($name, 'Repository');
        }

        // Str::studly can strip a name down to nothing (e.g. "_" or "__").
        if ($name === '' || preg_match(self::PATTERN, $name) !== 1) {
            throw RepositoryException::invalidClassName($name);
        }

        if (in_array(strtolower($name), self::RESERVED, true)) {
            throw RepositoryException::reservedClassName($name);
        }

        return $name;
    }

    /**
     * Determine whether a string is a valid bare class name, without throwing.
     */
    public static function isValid(string $name): bool
    {
        try {
            self::normalise($name);

            return true;
        } catch (RepositoryException) {
            return false;
        }
    }
}
