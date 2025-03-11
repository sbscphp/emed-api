<?php

namespace App\Repositories\Admission;

use App\Models\Admission;

class AdmissionRepository implements AdmissionInterface
{
    /**
     * Retrieve a collection of Admission from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Admission::all();
    }


    /**
     * Create new Admission in the database.
     * 
     * @param array $data
     * @return \App\Models\Admission
     */
    public function create(array $data)
    {
        return Admission::create($data);
    }


    /**
     * Update an existing Admission in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Admission
     */
    public function update(array $data, $id)
    {
        $record = Admission::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Admission from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Admission::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Admission in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Admission
     */
    public function find($id)
    {
        return Admission::find($id);
    }


    /**
     * Find an existing Admission in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Admission
     */
    public function findByAttribute($attr, $value)
    {
        return Admission::where($attr, $value)->first();
    }
}
