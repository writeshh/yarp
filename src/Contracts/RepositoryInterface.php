<?php

namespace Writeshh\Yarp\Contracts;

interface RepositoryInterface
{
    /**
     * Get all records
     *
     * @return void
     */
    public function all();

    /**
     * Get a record by it's ID
     *
     * @param string|int $id
     * @return void
     */
    public function show(string|int $id);

    /**
     * Create a record
     *
     * @param array $data
     * @return void
     */
    public function create(array $data);

    /**
     * Undocumented function
     *
     * @param array $data
     * @param string|int $id
     * @return void
     */
    public function update(array $data, string|int $id);

    /**
     * Undocumented function
     *
     * @param string|int $id
     * @return void
     */
    public function delete(string|int $id);
}
