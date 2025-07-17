<?php

namespace Writeshh\Yarp\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Get all records
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Get paginated records
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    
    /**
     * Get a record by it's ID
     *
     * @param string|int $id
     * @return Model|null
     */
    public function show(string|int $id): ?Model;

    /**
     * Get a record by it's ID or fail
     *
     * @param string|int $id
     * @return Model
     */
    public function findOrFail(string|int $id): Model;
    
    /**
     * Create a record
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * Update a record
     *
     * @param array $data
     * @param string|int $id
     * @return bool
     */
    public function update(array $data, string|int $id): bool;

    /**
     * Delete a record
     *
     * @param string|int $id
     * @return bool
     */
    public function delete(string|int $id): bool;
    
    /**
     * Get model instance
     *
     * @return Model
     */
    public function getModel(): Model;
    
    /**
     * Get a query builder instance
     *
     * @return Builder
     */
    public function query(): Builder;
    
    /**
     * Get records based on where condition
     * 
     * @param string $column
     * @param mixed $value
     * @return Collection
     */
    public function findWhere(string $column, mixed $value): Collection;
    
    /**
     * Get records with given relations
     * 
     * @param array $relations
     * @return Collection
     */
    public function with(array $relations): Collection;
}
