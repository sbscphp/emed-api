<?php

namespace App\Repositories\LaboratoryResult;

/**
 * Interface LaboratoryResultInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the LaboratoryResult model.
 */
interface LaboratoryResultInterface
{
    /**
     * Retrieve all LaboratoryResult from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new LaboratoryResult in the database.
     * 
     * @param array $data
     * @return \App\Models\LaboratoryResult
     */
    public function create(array $data);


    /**
     * Update an existing LaboratoryResult in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function update(array $data, $id);


    /**
     * Delete an existing LaboratoryResult from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing LaboratoryResult in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function find($id);


    /**
     * Find an existing LaboratoryResult in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\LaboratoryResult
     */
    public function findByAttribute($attr, $value);
}
