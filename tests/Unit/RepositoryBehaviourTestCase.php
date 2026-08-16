<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\TestCase;

/**
 * The full behavioural contract, run once per repository flavour.
 *
 * Both `BaseRepository` subclasses and classes using `InteractsWithRepository`
 * directly must pass every one of these, which is what keeps the two generated
 * styles from drifting apart.
 */
abstract class RepositoryBehaviourTestCase extends TestCase
{
    /** @var RepositoryInterface<User> */
    protected RepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->makeRepository();
    }

    /**
     * @return RepositoryInterface<User>
     */
    abstract protected function makeRepository(): RepositoryInterface;

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_returns_every_record(): void
    {
        $this->makeUser();
        $this->makeUser();

        $result = $this->repository->all();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_limits_selected_columns(): void
    {
        $this->makeUser(['name' => 'Ada']);

        $user = $this->repository->first(['id', 'name']);

        $this->assertNotNull($user);
        $this->assertSame('Ada', $user->name);
        $this->assertArrayNotHasKey('email', $user->getAttributes());
    }

    #[Test]
    public function it_finds_a_record_by_key(): void
    {
        $user = $this->makeUser(['name' => 'Grace']);

        $this->assertSame('Grace', $this->repository->find($user->id)?->name);
    }

    #[Test]
    public function it_returns_null_when_finding_a_missing_key(): void
    {
        $this->assertNull($this->repository->find(9999));
    }

    #[Test]
    public function it_throws_when_find_or_fail_misses(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOrFail(9999);
    }

    #[Test]
    public function it_finds_many_records_by_key(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();
        $this->makeUser();

        $result = $this->repository->findMany([$first->id, $second->id]);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_gets_the_first_record(): void
    {
        $this->makeUser(['name' => 'First']);
        $this->makeUser(['name' => 'Second']);

        $this->assertSame('First', $this->repository->first()?->name);
    }

    #[Test]
    public function it_throws_when_first_or_fail_misses(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->firstOrFail();
    }

    #[Test]
    public function it_finds_records_matching_a_column(): void
    {
        $this->makeUser(['email' => 'ada@example.test']);
        $this->makeUser(['email' => 'grace@example.test']);

        $result = $this->repository->findWhere('email', 'ada@example.test');

        $this->assertCount(1, $result);
        $this->assertSame('ada@example.test', $result->first()?->email);
    }

    #[Test]
    public function it_finds_records_using_an_operator(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $this->assertCount(1, $this->repository->findWhere('score', '>', 50));
    }

    #[Test]
    public function it_finds_the_first_record_matching_a_column(): void
    {
        $this->makeUser(['name' => 'Ada', 'score' => 10]);
        $this->makeUser(['name' => 'Grace', 'score' => 10]);

        $this->assertSame('Ada', $this->repository->firstWhere('score', 10)?->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_paginates_with_a_total(): void
    {
        foreach (range(1, 7) as $ignored) {
            $this->makeUser();
        }

        $page = $this->repository->paginate(3);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(7, $page->total());
        $this->assertCount(3, $page->items());
    }

    #[Test]
    public function it_defaults_the_page_size_to_the_configured_value(): void
    {
        config()->set('yarp.pagination.per_page', 2);

        foreach (range(1, 5) as $ignored) {
            $this->makeUser();
        }

        $this->assertCount(2, $this->repository->paginate()->items());
    }

    #[Test]
    public function it_simple_paginates(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->makeUser();
        }

        $page = $this->repository->simplePaginate(2);

        $this->assertInstanceOf(Paginator::class, $page);
        $this->assertCount(2, $page->items());
    }

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_creates_a_record(): void
    {
        $user = $this->repository->create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
        ]);

        $this->assertTrue($user->exists);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    #[Test]
    public function it_creates_many_records(): void
    {
        $created = $this->repository->createMany([
            ['name' => 'Ada', 'email' => 'ada@example.test'],
            ['name' => 'Grace', 'email' => 'grace@example.test'],
        ]);

        $this->assertCount(2, $created);
        $this->assertSame(2, User::query()->count());
    }

    #[Test]
    public function it_bulk_inserts_records(): void
    {
        $now = now()->toDateTimeString();

        $this->assertTrue($this->repository->insert([
            ['name' => 'Ada', 'email' => 'ada@example.test', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Grace', 'email' => 'grace@example.test', 'created_at' => $now, 'updated_at' => $now],
        ]));

        $this->assertSame(2, User::query()->count());
    }

    #[Test]
    public function it_treats_an_empty_bulk_insert_as_a_no_op(): void
    {
        $this->assertTrue($this->repository->insert([]));
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_updates_a_record_and_returns_the_fresh_model(): void
    {
        $user = $this->makeUser(['name' => 'Ada']);

        $updated = $this->repository->update($user->id, ['name' => 'Ada Lovelace']);

        $this->assertNotNull($updated);
        $this->assertSame('Ada Lovelace', $updated->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ada Lovelace']);
    }

    #[Test]
    public function it_returns_null_when_updating_a_missing_record(): void
    {
        $this->assertNull($this->repository->update(9999, ['name' => 'Nobody']));
    }

    #[Test]
    public function it_mass_updates_matching_records(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $affected = $this->repository->where('active', true)->updateWhere(['score' => 5]);

        $this->assertSame(2, $affected);
        $this->assertSame(2, User::query()->where('score', 5)->count());
    }

    #[Test]
    public function it_updates_or_creates(): void
    {
        $this->repository->updateOrCreate(['email' => 'ada@example.test'], ['name' => 'Ada']);
        $this->repository->updateOrCreate(['email' => 'ada@example.test'], ['name' => 'Ada Lovelace']);

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', ['name' => 'Ada Lovelace']);
    }

    #[Test]
    public function it_gets_the_first_record_or_creates_it(): void
    {
        $first = $this->repository->firstOrCreate(['email' => 'ada@example.test'], ['name' => 'Ada']);
        $second = $this->repository->firstOrCreate(['email' => 'ada@example.test'], ['name' => 'Ignored']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_deletes_a_record(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($this->repository->delete($user->id));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_returns_false_when_deleting_a_missing_record(): void
    {
        $this->assertFalse($this->repository->delete(9999));
    }

    #[Test]
    public function it_mass_deletes_matching_records(): void
    {
        $this->makeUser(['active' => false]);
        $this->makeUser(['active' => false]);
        $this->makeUser(['active' => true]);

        $this->assertSame(2, $this->repository->where('active', false)->deleteWhere());
        $this->assertSame(1, User::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Mass-operation guard
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_refuses_an_unconstrained_mass_delete(): void
    {
        $this->makeUser();

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessageMatches('/without any constraints/');

        $this->repository->deleteWhere();
    }

    #[Test]
    public function it_refuses_an_unconstrained_mass_update(): void
    {
        $this->makeUser();

        $this->expectException(RepositoryException::class);

        $this->repository->updateWhere(['score' => 1]);
    }

    #[Test]
    public function it_leaves_the_table_intact_when_the_guard_trips(): void
    {
        $this->makeUser();

        try {
            $this->repository->deleteWhere();
        } catch (RepositoryException) {
            // expected
        }

        $this->assertSame(1, User::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Aggregates
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_counts_records(): void
    {
        $this->makeUser();
        $this->makeUser();

        $this->assertSame(2, $this->repository->count());
    }

    #[Test]
    public function it_counts_records_matching_a_constraint(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertSame(1, $this->repository->where('active', true)->count());
    }

    #[Test]
    public function it_reports_existence(): void
    {
        $this->assertFalse($this->repository->exists());
        $this->assertTrue($this->repository->doesntExist());

        $this->makeUser();

        $this->assertTrue($this->repository->exists());
        $this->assertFalse($this->repository->doesntExist());
    }

    #[Test]
    public function it_aggregates_a_column(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 30]);

        $this->assertSame(40, (int) $this->repository->sum('score'));
        $this->assertSame(20.0, (float) $this->repository->avg('score'));
        $this->assertSame(10, (int) $this->repository->min('score'));
        $this->assertSame(30, (int) $this->repository->max('score'));
    }

    #[Test]
    public function it_plucks_a_column(): void
    {
        $this->makeUser(['name' => 'Ada']);
        $this->makeUser(['name' => 'Grace']);

        $names = $this->repository->pluck('name');

        $this->assertInstanceOf(BaseCollection::class, $names);
        $this->assertSame(['Ada', 'Grace'], $names->all());
    }

    #[Test]
    public function it_plucks_a_column_keyed_by_another(): void
    {
        $user = $this->makeUser(['name' => 'Ada']);

        $this->assertSame([$user->id => 'Ada'], $this->repository->pluck('name', 'id')->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Iteration
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_chunks_records(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->makeUser();
        }

        $seen = 0;

        $this->repository->chunk(2, function (Collection $chunk) use (&$seen): void {
            $seen += $chunk->count();
        });

        $this->assertSame(5, $seen);
    }

    #[Test]
    public function it_chunks_records_by_id(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->makeUser();
        }

        $seen = 0;

        $this->repository->chunkById(2, function (Collection $chunk) use (&$seen): void {
            $seen += $chunk->count();
        });

        $this->assertSame(5, $seen);
    }

    #[Test]
    public function it_streams_records_with_a_cursor(): void
    {
        $this->makeUser();
        $this->makeUser();

        $cursor = $this->repository->cursor();

        $this->assertInstanceOf(LazyCollection::class, $cursor);
        $this->assertSame(2, $cursor->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Fluent constraints
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_chains_constraints(): void
    {
        $this->makeUser(['name' => 'Low', 'score' => 1, 'active' => true]);
        $this->makeUser(['name' => 'High', 'score' => 99, 'active' => true]);
        $this->makeUser(['name' => 'Inactive', 'score' => 99, 'active' => false]);

        $result = $this->repository
            ->where('active', true)
            ->where('score', '>', 50)
            ->get();

        $this->assertCount(1, $result);
        $this->assertSame('High', $result->first()?->name);
    }

    #[Test]
    public function it_filters_with_where_in(): void
    {
        $ada = $this->makeUser(['name' => 'Ada']);
        $this->makeUser(['name' => 'Grace']);

        $this->assertCount(1, $this->repository->whereIn('id', [$ada->id])->get());
        $this->assertCount(1, $this->repository->whereNotIn('id', [$ada->id])->get());
    }

    #[Test]
    public function it_accepts_a_closure_constraint(): void
    {
        $this->makeUser(['score' => 5]);
        $this->makeUser(['score' => 50]);

        $result = $this->repository->where(function ($query): void {
            $query->where('score', '>', 10);
        })->get();

        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_accepts_an_array_constraint(): void
    {
        $this->makeUser(['name' => 'Ada', 'active' => true]);
        $this->makeUser(['name' => 'Grace', 'active' => false]);

        $this->assertCount(1, $this->repository->where(['active' => true])->get());
    }

    #[Test]
    public function it_eager_loads_relations(): void
    {
        $user = $this->makeUser();
        $this->makePost($user);

        $loaded = $this->repository->with('posts')->first();

        $this->assertTrue($loaded?->relationLoaded('posts'));
        $this->assertCount(1, $loaded->posts);
    }

    #[Test]
    public function it_eager_loads_relation_counts(): void
    {
        $user = $this->makeUser();
        $this->makePost($user);
        $this->makePost($user);

        $loaded = $this->repository->withCount('posts')->first();

        $this->assertSame(2, (int) $loaded?->getAttribute('posts_count'));
    }

    #[Test]
    public function it_orders_records(): void
    {
        $this->makeUser(['name' => 'Ada', 'score' => 1]);
        $this->makeUser(['name' => 'Grace', 'score' => 99]);

        $this->assertSame('Grace', $this->repository->orderBy('score', 'desc')->first()?->name);
        $this->assertSame('Ada', $this->repository->orderBy('score')->first()?->name);
    }

    #[Test]
    public function it_orders_by_recency(): void
    {
        $old = $this->makeUser(['name' => 'Old']);
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $this->makeUser(['name' => 'New']);

        $this->assertSame('New', $this->repository->latest()->first()?->name);
        $this->assertSame('Old', $this->repository->oldest()->first()?->name);
    }

    #[Test]
    public function it_limits_and_offsets(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->makeUser();
        }

        $this->assertCount(2, $this->repository->limit(2)->get());
        $this->assertCount(3, $this->repository->orderBy('id')->offset(2)->limit(10)->get());
    }

    #[Test]
    public function it_applies_a_model_scope(): void
    {
        $this->makeUser(['active' => true]);
        $this->makeUser(['active' => false]);

        $this->assertCount(1, $this->repository->scope('active')->get());
    }

    #[Test]
    public function it_applies_a_model_scope_with_parameters(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $this->assertCount(1, $this->repository->scope('scoredAbove', [50])->get());
    }

    #[Test]
    public function it_applies_a_raw_callback(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $result = $this->repository->tap(function ($query): void {
            $query->where('score', '>', 50);
        })->get();

        $this->assertCount(1, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Escape hatches
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_exposes_the_underlying_model(): void
    {
        $this->assertInstanceOf(User::class, $this->repository->getModel());
    }

    #[Test]
    public function it_hands_over_the_pending_builder(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $result = $this->repository->where('score', '>', 50)->query()->get();

        $this->assertCount(1, $result);
    }

    #[Test]
    public function new_query_ignores_pending_constraints(): void
    {
        $this->makeUser(['score' => 10]);
        $this->makeUser(['score' => 90]);

        $this->repository->where('score', '>', 50);

        $this->assertSame(2, $this->repository->newQuery()->count());
    }

    #[Test]
    public function it_runs_a_callback_in_a_transaction(): void
    {
        $result = $this->repository->transaction(function (RepositoryInterface $repository) {
            $repository->create(['name' => 'Ada', 'email' => 'ada@example.test']);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_rolls_back_a_failed_transaction(): void
    {
        try {
            $this->repository->transaction(function (RepositoryInterface $repository): void {
                $repository->create(['name' => 'Ada', 'email' => 'ada@example.test']);

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, User::query()->count());
    }
}
