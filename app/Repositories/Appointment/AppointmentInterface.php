<?php

namespace App\Repositories\Appointment;

/**
 * Interface AppointmentInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Appointment model.
 */
interface AppointmentInterface
{
    /**
     * Retrieve all Appointment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Appointment in the database.
     * 
     * @param array $data
     * @return \App\Models\Appointment
     */
    public function create(array $data);


    /**
     * Update an existing Appointment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Appointment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Appointment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function find($id);


    /**
     * Find an existing Appointment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Appointment
     */
    public function findByAttribute($attr, $value);
}
