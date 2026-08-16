<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Services\RepositoryGenerator;
use Writeshh\Yarp\Tests\Fixtures\Models\User;

/**
 * Generates a repository, loads the file it wrote, and drives it against a real
 * database.
 *
 * The other generator tests assert the output parses; this one asserts it works.
 * Between them, a stub that produces valid-but-broken PHP cannot ship.
 */
class GeneratedRepositoryWorksTest extends GeneratorTestCase
{
    /**
     * A fresh namespace per test, since classes cannot be unloaded once required.
     */
    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->namespace = 'YarpGenerated'.bin2hex(random_bytes(6));

        config()->set('yarp.model.namespace', 'Writeshh\\Yarp\\Tests\\Fixtures\\Models');
        config()->set('yarp.repository.namespace', $this->namespace);
        config()->set('yarp.interface.namespace', $this->namespace.'\\Contracts');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function flavours(): array
    {
        return [
            'extended' => [RepositoryGenerator::TYPE_EXTENDED],
            'standalone' => [RepositoryGenerator::TYPE_STANDALONE],
        ];
    }

    #[Test]
    #[DataProvider('flavours')]
    public function a_generated_repository_can_query_the_database(string $type): void
    {
        $repository = $this->generateAndInstantiate($type);

        $this->makeUser(['name' => 'Ada', 'score' => 99, 'active' => true]);
        $this->makeUser(['name' => 'Grace', 'score' => 10, 'active' => false]);

        $this->assertSame(2, $repository->count());
        $this->assertSame('Ada', $repository->where('active', true)->first()?->name);
        $this->assertSame('Ada', $repository->orderBy('score', 'desc')->first()?->name);
    }

    #[Test]
    #[DataProvider('flavours')]
    public function a_generated_repository_can_write(string $type): void
    {
        $repository = $this->generateAndInstantiate($type);

        $created = $repository->create(['name' => 'Ada', 'email' => 'ada@example.test']);
        $this->assertTrue($created->exists);

        $updated = $repository->update($created->id, ['name' => 'Ada Lovelace']);
        $this->assertSame('Ada Lovelace', $updated?->name);

        $this->assertTrue($repository->delete($created->id));
        $this->assertSame(0, $repository->count());
    }

    #[Test]
    #[DataProvider('flavours')]
    public function a_generated_repository_satisfies_the_contract(string $type): void
    {
        $repository = $this->generateAndInstantiate($type);

        $this->assertInstanceOf(RepositoryInterface::class, $repository);
        $this->assertInstanceOf($this->namespace.'\\Contracts\\UserRepositoryInterface', $repository);
        $this->assertInstanceOf(User::class, $repository->getModel());
    }

    #[Test]
    #[DataProvider('flavours')]
    public function a_generated_repository_keeps_the_mass_operation_guard(string $type): void
    {
        $repository = $this->generateAndInstantiate($type);
        $this->makeUser();

        $this->expectException(RepositoryException::class);

        $repository->deleteWhere();
    }

    /**
     * @return RepositoryInterface<User>
     */
    private function generateAndInstantiate(string $type): RepositoryInterface
    {
        $this->app->make(RepositoryGenerator::class)->generate('User', $type);

        require_once $this->interfacePath();
        require_once $this->repositoryPath();

        /** @var class-string<RepositoryInterface<User>> $class */
        $class = $this->namespace.'\\UserRepository';

        return new $class(new User);
    }
}
