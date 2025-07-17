<?php

namespace Writeshh\Yarp\Tests\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Tests\Models\User;

class DirectUserRepository implements RepositoryInterface
{
    protected User $model;
    
    public function __construct(User $model)
    {
        $this->model = $model;
    }
    
    public function all(): Collection
    {
        return $this->model->all();
    }
    
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }
    
    public function show(string|int $id): ?Model
    {
        return $this->model->find($id);
    }
    
    public function findOrFail(string|int $id): Model
    {
        return $this->model->findOrFail($id);
    }
    
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }
    
    public function update(array $data, string|int $id): bool
    {
        $record = $this->model->find($id);
        
        if (!$record) {
            return false;
        }
        
        return $record->update($data);
    }
    
    public function delete(string|int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }
    
    public function getModel(): Model
    {
        return $this->model;
    }
    
    public function query(): Builder
    {
        return $this->model->newQuery();
    }
    
    public function findWhere(string $column, mixed $value): Collection
    {
        return $this->model->where($column, $value)->get();
    }
    
    public function with(array $relations): Collection
    {
        return $this->model->with($relations)->get();
    }
}
