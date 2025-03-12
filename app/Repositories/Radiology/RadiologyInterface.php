<?php

namespace App\Repositories\Radiology;

/**
 * Interface RadiologyInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Radiology model.
 */
interface RadiologyInterface
{
    /**
     * Retrieve all Radiology from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Radiology in the database.
     * 
     * @param array $data
     * @return \App\Models\Radiology
     */
    public function create(array $data);


    /**
     * Update an existing Radiology in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Radiology from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Radiology in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function find($id);


    /**
     * Find an existing Radiology in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Radiology
     */
    public function findByAttribute($attr, $value);
}
