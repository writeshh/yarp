<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Criteria\OrderBy;
use Writeshh\Yarp\Criteria\Where;
use Writeshh\Yarp\Criteria\WhereIn;
use Writeshh\Yarp\Criteria\WithRelations;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\Fixtures\Repositories\UserRepository;
use Writeshh\Yarp\Tests\TestCase;

class CriteriaTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository(new User);
    }

    #[Test]
    public function it_applies_a_where_criterion(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertCount(1, $this->repository->pushCriteria(new Where('active', true))->get());
    }

    #[Test]
    public function a_where_criterion_accepts_an_operator(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $this->assertCount(1, $this->repository->pushCriteria(new Where('score', 50, '>'))->get());
    }

    #[Test]
    public function it_applies_a_where_in_criterion(): void
    {
        $ada = $this->makeUser();
        $this->makeUser();

        $this->assertCount(1, $this->repository->pushCriteria(new WhereIn('id', [$ada->id]))->get());
        $this->assertCount(1, $this->repository->pushCriteria(new WhereIn('id', [$ada->id], negate: true))->get());
    }

    #[Test]
    public function it_applies_an_order_by_criterion(): void
    {
        $this->makeUser(['name' => 'Ada', 'score' => 1]);
        $this->makeUser(['name' => 'Grace', 'score' => 99]);

        $this->assertSame('Grace', $this->repository->pushCriteria(new OrderBy('score', 'desc'))->first()?->name);
    }

    #[Test]
    public function an_order_by_criterion_rejects_an_unknown_direction(): void
    {
        $this->makeUser(['name' => 'Ada', 'score' => 1]);
        $this->makeUser(['name' => 'Grace', 'score' => 99]);

        // Anything that is not "desc" falls back to ascending rather than
        // reaching the SQL, so a user-supplied direction cannot be injected.
        $ordered = $this->repository->pushCriteria(new OrderBy('score', 'desc; drop table users'))->get();

        $this->assertSame('Ada', $ordered->first()?->name);
        $this->assertSame(2, User::query()->count());
    }

    #[Test]
    public function it_applies_a_with_relations_criterion(): void
    {
        $user = $this->makeUser();
        $this->makePost($user);

        $loaded = $this->repository->pushCriteria(new WithRelations('posts'))->first();

        $this->assertTrue($loaded?->relationLoaded('posts'));
    }

    #[Test]
    public function it_composes_several_criteria(): void
    {
        $this->makeUser(['name' => 'Low', 'score' => 1, 'active' => true]);
        $this->makeUser(['name' => 'High', 'score' => 99, 'active' => true]);
        $this->makeUser(['name' => 'Hidden', 'score' => 99, 'active' => false]);

        $result = $this->repository->pushCriteria(
            new Where('active', true),
            new OrderBy('score', 'desc'),
        )->get();

        $this->assertCount(2, $result);
        $this->assertSame('High', $result->first()?->name);
    }

    #[Test]
    public function criteria_are_discarded_after_execution(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->repository->pushCriteria(new Where('active', true))->get();

        $this->assertSame(2, $this->repository->count());
    }

    #[Test]
    public function a_criterion_satisfies_the_mass_operation_guard(): void
    {
        $this->makeUser(['active' => false]);
        $this->makeUser(['active' => true]);

        $this->assertSame(1, $this->repository->pushCriteria(new Where('active', false))->deleteWhere());
    }

    #[Test]
    public function custom_criteria_receive_the_repository(): void
    {
        $this->makeUser(['name' => 'Ada']);

        $criterion = new class implements Criterion
        {
            public ?RepositoryInterface $seen = null;

            public function apply(Builder $query, RepositoryInterface $repository): Builder
            {
                $this->seen = $repository;

                return $query->where('name', 'Ada');
            }
        };

        $this->assertCount(1, $this->repository->pushCriteria($criterion)->get());
        $this->assertSame($this->repository, $criterion->seen);
    }
}
