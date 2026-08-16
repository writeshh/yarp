<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\Fixtures\Repositories\UserRepository;
use Writeshh\Yarp\Tests\TestCase;

/**
 * Repositories are usually resolved as singletons and injected once, so the same
 * instance serves many calls in a request. Constraints applied for one call must
 * never survive into the next — otherwise a `->where('id', $someoneElse)` from an
 * earlier call silently narrows a later, unrelated query.
 */
class PendingQueryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository(new User);
    }

    #[Test]
    public function constraints_do_not_leak_into_the_next_call(): void
    {
        $this->makeUser(['name' => 'Ada', 'score' => 99]);
        $this->makeUser(['name' => 'Grace', 'score' => 1]);

        $this->assertCount(1, $this->repository->where('score', '>', 50)->get());
        $this->assertCount(2, $this->repository->all());
    }

    #[Test]
    public function constraints_do_not_leak_after_a_find(): void
    {
        $ada = $this->makeUser(['name' => 'Ada', 'active' => false]);
        $this->makeUser(['name' => 'Grace', 'active' => true]);

        $this->assertNull($this->repository->where('active', true)->find($ada->id));
        $this->assertNotNull($this->repository->find($ada->id));
    }

    #[Test]
    public function constraints_do_not_leak_after_an_aggregate(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertSame(1, $this->repository->where('active', true)->count());
        $this->assertSame(2, $this->repository->count());
    }

    #[Test]
    public function constraints_do_not_leak_after_pagination(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertSame(1, $this->repository->where('active', true)->paginate(10)->total());
        $this->assertSame(2, $this->repository->paginate(10)->total());
    }

    #[Test]
    public function taking_the_builder_resets_the_repository(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->repository->where('active', true)->query()->get();

        $this->assertSame(2, $this->repository->count());
    }

    #[Test]
    public function reset_discards_pending_constraints(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertSame(2, $this->repository->where('active', true)->reset()->count());
    }

    #[Test]
    public function reset_also_clears_the_mass_operation_guard(): void
    {
        $this->makeUser();

        $this->expectException(RepositoryException::class);

        $this->repository->where('active', true)->reset()->deleteWhere();
    }

    #[Test]
    public function writes_are_unaffected_by_pending_constraints(): void
    {
        $this->makeUser(['name' => 'Existing', 'score' => 0]);

        // A pending constraint that matches nothing must not stop a create.
        $this->repository->where('score', '>', 1000);

        $created = $this->repository->create(['name' => 'Ada', 'email' => 'ada@example.test']);

        $this->assertTrue($created->exists);
    }

    #[Test]
    public function a_failed_mass_operation_leaves_the_repository_usable(): void
    {
        $this->makeUser(['active' => true]);

        try {
            $this->repository->deleteWhere();
        } catch (RepositoryException) {
            // expected
        }

        $this->assertSame(1, $this->repository->count());
        $this->assertSame(1, $this->repository->where('active', true)->deleteWhere());
    }
}
