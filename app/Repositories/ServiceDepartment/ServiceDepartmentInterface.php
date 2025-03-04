<?php

namespace App\Repositories\ServiceDepartment;

/**
 * Interface ServiceDepartmentInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the ServiceDepartment model.
 */
interface ServiceDepartmentInterface
{
    /**
     * Retrieve all ServiceDepartment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new ServiceDepartment in the database.
     * 
     * @param array $data
     * @return \App\Models\ServiceDepartment
     */
    public function create(array $data);


    /**
     * Update an existing ServiceDepartment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function update(array $data, $id);


    /**
     * Delete an existing ServiceDepartment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing ServiceDepartment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function find($id);


    /**
     * Find an existing ServiceDepartment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\ServiceDepartment
     */
    public function findByAttribute($attr, $value);
}
