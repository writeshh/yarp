<?php

namespace Writeshh\Yarp;

use Illuminate\Database\Eloquent\Model;
use Writeshh\Yarp\Contracts\RepositoryInterface;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Model
     */
    protected $model;
    
    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }
    
    /**
     * Get all records
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return $this->model->all();
    }
    
    /**
     * Get a record by it's ID
     *
     * @param string|int $id
     * @return Model|null
     */
    public function show(string|int $id)
    {
        return $this->model->find($id);
    }
    
    /**
     * Create a record
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }
    
    /**
     * Update a record
     *
     * @param array $data
     * @param string|int $id
     * @return bool
     */
    public function update(array $data, string|int $id)
    {
        $record = $this->model->find($id);
        
        if (!$record) {
            return false;
        }
        
        return $record->update($data);
    }
    
    /**
     * Delete a record
     *
     * @param string|int $id
     * @return bool
     */
    public function delete(string|int $id)
    {
        return $this->model->destroy($id) > 0;
    }
    
    /**
     * Get model instance
     *
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }
}
