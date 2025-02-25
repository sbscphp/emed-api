<?php

namespace App\Repositories\Patient;

use App\Models\Patient;

class PatientRepository implements PatientInterface
{
    /**
     * Retrieve a collection of Patient from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Patient::all();
    }


    /**
     * Create new Patient in the database.
     * 
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create(array $data)
    {
        return Patient::create($data);
    }


    /**
     * Update an existing Patient in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update(array $data, $id)
    {
        $record = Patient::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Patient from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Patient::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Patient in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Patient
     */
    public function find($id)
    {
        return Patient::find($id);
    }


    /**
     * Find an existing Patient in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Patient
     */
    public function findByAttribute($attr, $value)
    {
        return Patient::where($attr, $value)->first();
    }
}
