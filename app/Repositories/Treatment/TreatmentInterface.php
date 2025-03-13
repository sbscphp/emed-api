<?php

namespace App\Repositories\Treatment;

/**
 * Interface TreatmentInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Treatment model.
 */
interface TreatmentInterface
{
    /**
     * Retrieve all Treatment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Treatment in the database.
     * 
     * @param array $data
     * @return \App\Models\Treatment
     */
    public function create(array $data);


    /**
     * Update an existing Treatment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Treatment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Treatment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function find($id);


    /**
     * Find an existing Treatment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Treatment
     */
    public function findByAttribute($attr, $value);
}
