<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Support\ClassName;

class ClassNameTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validNames(): array
    {
        return [
            'already studly' => ['User', 'User'],
            'lowercase' => ['user', 'User'],
            'snake case' => ['blog_post', 'BlogPost'],
            'leading underscore' => ['_internal', 'Internal'],
            'with digits' => ['Oauth2Client', 'Oauth2Client'],
            'redundant suffix stripped' => ['UserRepository', 'User'],
            'suffix alone is kept' => ['Repository', 'Repository'],
            'surrounding whitespace' => ['  User  ', 'User'],
        ];
    }

    #[Test]
    #[DataProvider('validNames')]
    public function it_normalises_valid_names(string $input, string $expected): void
    {
        $this->assertSame($expected, ClassName::normalise($input));
        $this->assertTrue(ClassName::isValid($input));
    }

    /**
     * Names that must be rejected. The traversal cases are the important ones:
     * every generated path is built by concatenating this value, so anything
     * containing a separator would let `make:repo` write outside the configured
     * repositories directory.
     *
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'parent traversal' => ['../../routes/web'],
            'windows traversal' => ['..\\..\\routes\\web'],
            'absolute path' => ['/etc/passwd'],
            'nested path' => ['Admin/User'],
            'namespaced' => ['App\\Models\\User'],
            'null byte' => ["User\0.php"],
            'newline' => ["User\nEvil"],
            'php extension' => ['User.php'],
            'leading digit' => ['1User'],
            'hyphen' => ['blog-post'],
            'space inside' => ['Blog Post'],
            'glob' => ['User*'],
            'dollar' => ['User$'],
            'underscores only' => ['___'],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_unusable_names(string $input): void
    {
        $this->assertFalse(ClassName::isValid($input));

        $this->expectException(RepositoryException::class);

        ClassName::normalise($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedNames(): array
    {
        return [
            'class' => ['class'],
            'interface' => ['interface'],
            'list' => ['list'],
            'match' => ['match'],
            'enum' => ['enum'],
            'static' => ['static'],
            'differently cased' => ['Class'],
        ];
    }

    #[Test]
    #[DataProvider('reservedNames')]
    public function it_rejects_reserved_keywords(string $input): void
    {
        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessageMatches('/reserved PHP keyword/');

        ClassName::normalise($input);
    }
}
