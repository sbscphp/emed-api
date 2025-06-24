<?php

namespace App\Repositories\EmergencyContact;

use App\Models\EmergencyContact;

class EmergencyContactRepository implements EmergencyContactInterface
{
    /**
     * Retrieve a collection of EmergencyContact from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return EmergencyContact::all();
    }


    /**
     * Create new EmergencyContact in the database.
     * 
     * @param array $data
     * @return \App\Models\EmergencyContact
     */
    public function create(array $data)
    {
        return EmergencyContact::create($data);
    }


    /**
     * Update an existing EmergencyContact in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function update(array $data, $id)
    {

        $record = EmergencyContact::where('patient_id', $id)->first();
        if($record){
          $record->update($data);
           return $record;
        }
      return null;
    }


    /**
     * Delete an existing EmergencyContact from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = EmergencyContact::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing EmergencyContact in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function find($id)
    {
        return EmergencyContact::find($id);
    }


    /**
     * Find an existing EmergencyContact in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\EmergencyContact
     */
    public function findByAttribute($attr, $value)
    {
        return EmergencyContact::where($attr, $value)->first();
    }
}
