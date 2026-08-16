<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Contracts;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;
use Writeshh\Yarp\Exceptions\RepositoryException;

/**
 * The contract every YARP repository fulfils.
 *
 * Methods fall into three groups:
 *
 *  - Fluent constraints (`where`, `with`, `orderBy`, `limit`, ...) return `$this`
 *    and accumulate onto a pending query.
 *  - Terminal operations (`all`, `find`, `get`, `paginate`, `count`, ...) execute
 *    that pending query and then reset it, so a long-lived repository instance
 *    never leaks constraints from one call into the next.
 *  - Escape hatches (`query`, `getModel`, `transaction`) hand you the underlying
 *    Eloquent primitives for anything the repository does not wrap.
 *
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    /**
     * Get every record, subject to any pending constraints.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Execute the pending query and get the results.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function get(array $columns = ['*']): Collection;

    /**
     * Find a single record by its primary key.
     *
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    public function find(int|string $id, array $columns = ['*']): ?Model;

    /**
     * Find a single record by its primary key or throw ModelNotFoundException.
     *
     * @param  array<int, string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model;

    /**
     * Find many records by primary key.
     *
     * @param  array<int, int|string>|BaseCollection<int, int|string>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function findMany(array|BaseCollection $ids, array $columns = ['*']): Collection;

    /**
     * Get the first record matching the pending query.
     *
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    public function first(array $columns = ['*']): ?Model;

    /**
     * Get the first record matching the pending query or throw ModelNotFoundException.
     *
     * @param  array<int, string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public function firstOrFail(array $columns = ['*']): Model;

    /**
     * Get every record matching a simple where constraint.
     *
     * @param  array<string, mixed>|string|Closure(Builder<TModel>): mixed  $column
     * @return Collection<int, TModel>
     */
    public function findWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): Collection;

    /**
     * Get the first record matching a simple where constraint.
     *
     * @param  array<string, mixed>|string|Closure(Builder<TModel>): mixed  $column
     * @return TModel|null
     */
    public function firstWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): ?Model;

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    /**
     * Paginate the records with a total count.
     *
     * Passing null for $perPage uses the `yarp.pagination.per_page` config value.
     *
     * @param  array<int, string>  $columns
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator;

    /**
     * Paginate the records without running a count query.
     *
     * @param  array<int, string>  $columns
     * @return Paginator<int, TModel>
     */
    public function simplePaginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator;

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    */

    /**
     * Create a record.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * Create many records, one model event per record.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return Collection<int, TModel>
     */
    public function createMany(array $records): Collection;

    /**
     * Bulk insert records in a single statement.
     *
     * Bypasses Eloquent entirely: no events, no casts, no timestamps.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insert(array $records): bool;

    /**
     * Update a record by primary key and return the fresh model.
     *
     * Returns null when no record matches the given key.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel|null
     */
    public function update(int|string $id, array $attributes): ?Model;

    /**
     * Update every record matching the pending query. Returns the number of rows affected.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateWhere(array $attributes): int;

    /**
     * Update the record matching $attributes, or create it if none exists.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * Get the first record matching $attributes, or create it if none exists.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function firstOrCreate(array $attributes, array $values = []): Model;

    /**
     * Delete a record by primary key.
     *
     * Returns false when no record matches the given key.
     */
    public function delete(int|string $id): bool;

    /**
     * Delete every record matching the pending query. Returns the number of rows affected.
     */
    public function deleteWhere(): int;

    /*
    |--------------------------------------------------------------------------
    | Soft deletes
    |--------------------------------------------------------------------------
    */

    /**
     * Include soft-deleted records in the pending query.
     *
     * @return $this
     *
     * @throws RepositoryException when the model is not soft-deletable
     */
    public function withTrashed(): static;

    /**
     * Restrict the pending query to soft-deleted records only.
     *
     * @return $this
     *
     * @throws RepositoryException when the model is not soft-deletable
     */
    public function onlyTrashed(): static;

    /**
     * Restore a soft-deleted record by primary key.
     *
     * @throws RepositoryException when the model is not soft-deletable
     */
    public function restore(int|string $id): bool;

    /**
     * Permanently delete a record by primary key, ignoring soft deletes.
     */
    public function forceDelete(int|string $id): bool;

    /*
    |--------------------------------------------------------------------------
    | Aggregates
    |--------------------------------------------------------------------------
    */

    /**
     * Count the records matching the pending query.
     */
    public function count(string $columns = '*'): int;

    /**
     * Determine whether any record matches the pending query.
     */
    public function exists(): bool;

    /**
     * Determine whether no record matches the pending query.
     */
    public function doesntExist(): bool;

    /**
     * Sum a column across the records matching the pending query.
     */
    public function sum(string $column): int|float;

    /**
     * Average a column across the records matching the pending query.
     */
    public function avg(string $column): int|float|null;

    /**
     * Get the minimum value of a column across the pending query.
     */
    public function min(string $column): mixed;

    /**
     * Get the maximum value of a column across the pending query.
     */
    public function max(string $column): mixed;

    /**
     * Get the values of a single column, optionally keyed by another.
     *
     * @return BaseCollection<array-key, mixed>
     */
    public function pluck(string $column, ?string $key = null): BaseCollection;

    /*
    |--------------------------------------------------------------------------
    | Iteration
    |--------------------------------------------------------------------------
    */

    /**
     * Process the matching records in chunks to keep memory flat.
     *
     * @param  Closure(Collection<int, TModel>, int): mixed  $callback
     */
    public function chunk(int $count, Closure $callback): bool;

    /**
     * Chunk by primary key, safe to use while modifying the records being iterated.
     *
     * @param  Closure(Collection<int, TModel>, int): mixed  $callback
     */
    public function chunkById(int $count, Closure $callback): bool;

    /**
     * Get a lazy collection that streams the results one model at a time.
     *
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection;

    /*
    |--------------------------------------------------------------------------
    | Fluent constraints
    |--------------------------------------------------------------------------
    */

    /**
     * Add a where constraint to the pending query.
     *
     * @param  array<string, mixed>|string|Closure(Builder<TModel>): mixed  $column
     * @return $this
     */
    public function where(array|string|Closure $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a "where in" constraint to the pending query.
     *
     * @param  array<int, mixed>|BaseCollection<int, mixed>  $values
     * @return $this
     */
    public function whereIn(string $column, array|BaseCollection $values): static;

    /**
     * Add a "where not in" constraint to the pending query.
     *
     * @param  array<int, mixed>|BaseCollection<int, mixed>  $values
     * @return $this
     */
    public function whereNotIn(string $column, array|BaseCollection $values): static;

    /**
     * Eager load relations on the pending query.
     *
     * @param  array<int|string, mixed>|string  $relations
     * @return $this
     */
    public function with(array|string $relations): static;

    /**
     * Eager load relation counts on the pending query.
     *
     * @param  array<int|string, mixed>|string  $relations
     * @return $this
     */
    public function withCount(array|string $relations): static;

    /**
     * Order the pending query.
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): static;

    /**
     * Order the pending query by newest first.
     *
     * @return $this
     */
    public function latest(?string $column = null): static;

    /**
     * Order the pending query by oldest first.
     *
     * @return $this
     */
    public function oldest(?string $column = null): static;

    /**
     * Limit the number of records returned by the pending query.
     *
     * @return $this
     */
    public function limit(int $value): static;

    /**
     * Skip records in the pending query.
     *
     * @return $this
     */
    public function offset(int $value): static;

    /**
     * Apply a model scope to the pending query.
     *
     * @param  array<int, mixed>  $parameters
     * @return $this
     */
    public function scope(string $scope, array $parameters = []): static;

    /**
     * Apply a raw callback to the pending query.
     *
     * @param  Closure(Builder<TModel>): mixed  $callback
     * @return $this
     */
    public function tap(Closure $callback): static;

    /**
     * Push one or more reusable criteria onto the pending query.
     *
     * @param  Criterion<TModel>  ...$criteria
     * @return $this
     */
    public function pushCriteria(Criterion ...$criteria): static;

    /**
     * Discard any pending constraints without executing them.
     *
     * @return $this
     */
    public function reset(): static;

    /*
    |--------------------------------------------------------------------------
    | Escape hatches
    |--------------------------------------------------------------------------
    */

    /**
     * Get the model instance the repository wraps.
     *
     * @return TModel
     */
    public function getModel(): Model;

    /**
     * Take ownership of the pending query builder, resetting the repository.
     *
     * Use this for anything the repository does not wrap:
     *
     *     $repository->where('active', true)->query()->whereJsonContains('tags', 'php')->get();
     *
     * @return Builder<TModel>
     */
    public function query(): Builder;

    /**
     * Get a brand new query builder, ignoring any pending constraints.
     *
     * @return Builder<TModel>
     */
    public function newQuery(): Builder;

    /**
     * Run a callback inside a database transaction.
     *
     * @template TReturn
     *
     * @param  Closure($this): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed;
}
