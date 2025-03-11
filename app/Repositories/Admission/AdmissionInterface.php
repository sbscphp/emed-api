<?php

namespace App\Repositories\Admission;

/**
 * Interface AdmissionInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Admission model.
 */
interface AdmissionInterface
{
    /**
     * Retrieve all Admission from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Admission in the database.
     * 
     * @param array $data
     * @return \App\Models\Admission
     */
    public function create(array $data);


    /**
     * Update an existing Admission in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Admission
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Admission from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Admission in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Admission
     */
    public function find($id);


    /**
     * Find an existing Admission in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Admission
     */
    public function findByAttribute($attr, $value);
}
