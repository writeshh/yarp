<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Concerns;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Exceptions\RepositoryException;

/**
 * The whole repository implementation, as a trait.
 *
 * `BaseRepository` is nothing more than this trait plus a constructor. Use the
 * trait directly when you would rather not inherit from a package class:
 *
 *     class UserRepository implements RepositoryInterface
 *     {
 *         use InteractsWithRepository;
 *
 *         public function __construct(protected Model $model) {}
 *     }
 *
 * The consuming class must declare a `$model` property holding the Eloquent
 * model the repository wraps.
 *
 * @template TModel of Model
 *
 * @property TModel $model
 */
trait InteractsWithRepository
{
    /**
     * Constraints accumulated by fluent calls, executed and discarded by the
     * next terminal operation.
     *
     * @var Builder<TModel>|null
     */
    protected ?Builder $pendingQuery = null;

    /**
     * Whether any constraint has been applied since the last reset.
     *
     * Guards `updateWhere()` and `deleteWhere()` against silently rewriting
     * every row in the table.
     */
    protected bool $constrained = false;

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function all(array $columns = ['*']): Collection
    {
        return $this->get($columns);
    }

    /** {@inheritDoc} */
    public function get(array $columns = ['*']): Collection
    {
        return $this->consume()->get($columns);
    }

    /** {@inheritDoc} */
    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->consume()->find($id, $columns);
    }

    /** {@inheritDoc} */
    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->consume()->findOrFail($id, $columns);
    }

    /** {@inheritDoc} */
    public function findMany(array|BaseCollection $ids, array $columns = ['*']): Collection
    {
        return $this->consume()->findMany($ids, $columns);
    }

    /** {@inheritDoc} */
    public function first(array $columns = ['*']): ?Model
    {
        return $this->consume()->first($columns);
    }

    /** {@inheritDoc} */
    public function firstOrFail(array $columns = ['*']): Model
    {
        return $this->consume()->firstOrFail($columns);
    }

    /** {@inheritDoc} */
    public function findWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): Collection
    {
        return $this->where(...func_get_args())->get();
    }

    /** {@inheritDoc} */
    public function firstWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): ?Model
    {
        return $this->where(...func_get_args())->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->consume()->paginate($this->resolvePerPage($perPage), $columns, $pageName, $page);
    }

    /** {@inheritDoc} */
    public function simplePaginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        return $this->consume()->simplePaginate($this->resolvePerPage($perPage), $columns, $pageName, $page);
    }

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function create(array $attributes): Model
    {
        return $this->newQuery()->create($attributes);
    }

    /** {@inheritDoc} */
    public function createMany(array $records): Collection
    {
        $created = $this->model->newCollection();

        foreach ($records as $attributes) {
            $created->push($this->create($attributes));
        }

        return $created;
    }

    /** {@inheritDoc} */
    public function insert(array $records): bool
    {
        if ($records === []) {
            return true;
        }

        return $this->newQuery()->insert($records);
    }

    /** {@inheritDoc} */
    public function update(int|string $id, array $attributes): ?Model
    {
        $record = $this->consume()->find($id);

        if ($record === null) {
            return null;
        }

        $record->update($attributes);

        return $record;
    }

    /** {@inheritDoc} */
    public function updateWhere(array $attributes): int
    {
        $query = $this->consumeConstrained(__FUNCTION__);

        return $query->update($attributes);
    }

    /** {@inheritDoc} */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->updateOrCreate($attributes, $values);
    }

    /** {@inheritDoc} */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->firstOrCreate($attributes, $values);
    }

    /** {@inheritDoc} */
    public function delete(int|string $id): bool
    {
        $record = $this->consume()->find($id);

        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }

    /** {@inheritDoc} */
    public function deleteWhere(): int
    {
        $query = $this->consumeConstrained(__FUNCTION__);

        return $query->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Soft deletes
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function withTrashed(): static
    {
        $this->guardSoftDeletes(__FUNCTION__);

        // Equivalent to SoftDeletes' own withTrashed(), but expressed against
        // the plain Builder API so it type-checks without the trait's magic.
        return $this->tap(fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class));
    }

    /** {@inheritDoc} */
    public function onlyTrashed(): static
    {
        $this->guardSoftDeletes(__FUNCTION__);

        return $this->tap(fn (Builder $query) => $query
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->deletedAtColumn()));
    }

    /** {@inheritDoc} */
    public function restore(int|string $id): bool
    {
        $this->guardSoftDeletes(__FUNCTION__);

        $record = $this->withTrashed()->consume()->find($id);

        if ($record === null) {
            return false;
        }

        return (bool) $record->restore();
    }

    /** {@inheritDoc} */
    public function forceDelete(int|string $id): bool
    {
        if ($this->usesSoftDeletes()) {
            $this->withTrashed();
        }

        $record = $this->consume()->find($id);

        if ($record === null) {
            return false;
        }

        return (bool) $record->forceDelete();
    }

    /*
    |--------------------------------------------------------------------------
    | Aggregates
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function count(string $columns = '*'): int
    {
        return $this->consume()->count($columns);
    }

    /** {@inheritDoc} */
    public function exists(): bool
    {
        return $this->consume()->exists();
    }

    /** {@inheritDoc} */
    public function doesntExist(): bool
    {
        return $this->consume()->doesntExist();
    }

    /** {@inheritDoc} */
    public function sum(string $column): int|float
    {
        return $this->consume()->sum($column);
    }

    /** {@inheritDoc} */
    public function avg(string $column): int|float|null
    {
        return $this->consume()->avg($column);
    }

    /** {@inheritDoc} */
    public function min(string $column): mixed
    {
        return $this->consume()->min($column);
    }

    /** {@inheritDoc} */
    public function max(string $column): mixed
    {
        return $this->consume()->max($column);
    }

    /** {@inheritDoc} */
    public function pluck(string $column, ?string $key = null): BaseCollection
    {
        return $this->consume()->pluck($column, $key);
    }

    /*
    |--------------------------------------------------------------------------
    | Iteration
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function chunk(int $count, Closure $callback): bool
    {
        return $this->consume()->chunk($count, $callback);
    }

    /** {@inheritDoc} */
    public function chunkById(int $count, Closure $callback): bool
    {
        return $this->consume()->chunkById($count, $callback);
    }

    /** {@inheritDoc} */
    public function cursor(): LazyCollection
    {
        return $this->consume()->cursor();
    }

    /*
    |--------------------------------------------------------------------------
    | Fluent constraints
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function where(array|string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        $arguments = func_get_args();

        return $this->tap(fn (Builder $query) => $query->where(...$arguments));
    }

    /** {@inheritDoc} */
    public function whereIn(string $column, array|BaseCollection $values): static
    {
        return $this->tap(fn (Builder $query) => $query->whereIn($column, $values));
    }

    /** {@inheritDoc} */
    public function whereNotIn(string $column, array|BaseCollection $values): static
    {
        return $this->tap(fn (Builder $query) => $query->whereNotIn($column, $values));
    }

    /** {@inheritDoc} */
    public function with(array|string $relations): static
    {
        return $this->tap(fn (Builder $query) => $query->with($relations));
    }

    /** {@inheritDoc} */
    public function withCount(array|string $relations): static
    {
        return $this->tap(fn (Builder $query) => $query->withCount($relations));
    }

    /** {@inheritDoc} */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        return $this->tap(fn (Builder $query) => $query->orderBy($column, $direction));
    }

    /** {@inheritDoc} */
    public function latest(?string $column = null): static
    {
        return $this->tap(fn (Builder $query) => $query->latest($column ?? $this->model->getCreatedAtColumn() ?? 'created_at'));
    }

    /** {@inheritDoc} */
    public function oldest(?string $column = null): static
    {
        return $this->tap(fn (Builder $query) => $query->oldest($column ?? $this->model->getCreatedAtColumn() ?? 'created_at'));
    }

    /** {@inheritDoc} */
    public function limit(int $value): static
    {
        return $this->tap(fn (Builder $query) => $query->limit($value));
    }

    /** {@inheritDoc} */
    public function offset(int $value): static
    {
        return $this->tap(fn (Builder $query) => $query->offset($value));
    }

    /** {@inheritDoc} */
    public function scope(string $scope, array $parameters = []): static
    {
        return $this->tap(fn (Builder $query) => $query->{$scope}(...$parameters));
    }

    /** {@inheritDoc} */
    public function tap(Closure $callback): static
    {
        $callback($this->pending());

        $this->constrained = true;

        return $this;
    }

    /**
     * @param  Criterion<TModel>  ...$criteria
     * @return $this
     */
    public function pushCriteria(Criterion ...$criteria): static
    {
        foreach ($criteria as $criterion) {
            $this->pendingQuery = $criterion->apply($this->pending(), $this);
            $this->constrained = true;
        }

        return $this;
    }

    /** {@inheritDoc} */
    public function reset(): static
    {
        $this->pendingQuery = null;
        $this->constrained = false;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Escape hatches
    |--------------------------------------------------------------------------
    */

    /** {@inheritDoc} */
    public function getModel(): Model
    {
        return $this->model;
    }

    /** {@inheritDoc} */
    public function query(): Builder
    {
        return $this->consume();
    }

    /** {@inheritDoc} */
    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /** {@inheritDoc} */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return $this->model->getConnection()->transaction(fn () => $callback($this), $attempts);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Get the pending query, creating it on first use.
     *
     * @return Builder<TModel>
     */
    protected function pending(): Builder
    {
        return $this->pendingQuery ??= $this->newQuery();
    }

    /**
     * Take the pending query and reset the repository, so the next call starts clean.
     *
     * @return Builder<TModel>
     */
    protected function consume(): Builder
    {
        $query = $this->pending();

        $this->reset();

        return $query;
    }

    /**
     * Take the pending query, refusing to hand it over unconstrained.
     *
     * Without this guard, a bare `deleteWhere()` would truncate the table.
     *
     * @return Builder<TModel>
     *
     * @throws RepositoryException
     */
    protected function consumeConstrained(string $method): Builder
    {
        if (! $this->constrained) {
            $this->reset();

            throw RepositoryException::unconstrainedMassOperation($method, static::class);
        }

        return $this->consume();
    }

    /**
     * Determine whether the wrapped model is soft-deletable.
     */
    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    /**
     * The model's soft-delete timestamp column, qualified with its table.
     */
    protected function deletedAtColumn(): string
    {
        /** @var string $column */
        $column = $this->model->getDeletedAtColumn();

        return $this->model->qualifyColumn($column);
    }

    /**
     * @throws RepositoryException
     */
    protected function guardSoftDeletes(string $method): void
    {
        if (! $this->usesSoftDeletes()) {
            throw RepositoryException::softDeletesNotSupported($this->model::class, $method);
        }
    }

    /**
     * Resolve the page size, falling back to config then to the model's own default.
     */
    protected function resolvePerPage(?int $perPage): int
    {
        if ($perPage !== null) {
            return $perPage;
        }

        $container = Container::getInstance();

        if ($container->bound('config')) {
            $configured = $container->make('config')->get('yarp.pagination.per_page');

            if (is_int($configured) && $configured > 0) {
                return $configured;
            }
        }

        return $this->model->getPerPage();
    }
}
