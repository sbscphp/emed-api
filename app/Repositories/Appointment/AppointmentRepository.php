<?php

namespace App\Repositories\Appointment;

use App\Models\Appointment;

class AppointmentRepository implements AppointmentInterface
{
    /**
     * Retrieve a collection of Appointment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Appointment::all();
    }


    /**
     * Create new Appointment in the database.
     * 
     * @param array $data
     * @return \App\Models\Appointment
     */
    public function create(array $data)
    {
        return Appointment::create($data);
    }


    /**
     * Update an existing Appointment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function update(array $data, $id)
    {
        $record = Appointment::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Appointment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Appointment::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Appointment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function find($id)
    {
        return Appointment::find($id);
    }


    /**
     * Find an existing Appointment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Appointment
     */
    public function findByAttribute($attr, $value)
    {
        return Appointment::where($attr, $value)->first();
    }
}
