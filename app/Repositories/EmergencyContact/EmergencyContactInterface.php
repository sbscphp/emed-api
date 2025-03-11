<?php

namespace App\Repositories\EmergencyContact;

/**
 * Interface EmergencyContactInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the EmergencyContact model.
 */
interface EmergencyContactInterface
{
    /**
     * Retrieve all EmergencyContact from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new EmergencyContact in the database.
     * 
     * @param array $data
     * @return \App\Models\EmergencyContact
     */
    public function create(array $data);


    /**
     * Update an existing EmergencyContact in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function update(array $data, $id);


    /**
     * Delete an existing EmergencyContact from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing EmergencyContact in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function find($id);


    /**
     * Find an existing EmergencyContact in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\EmergencyContact
     */
    public function findByAttribute($attr, $value);
}
