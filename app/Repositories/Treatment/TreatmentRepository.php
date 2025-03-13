<?php

namespace App\Repositories\Treatment;

use App\Models\Treatment;

class TreatmentRepository implements TreatmentInterface
{
    /**
     * Retrieve a collection of Treatment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Treatment::all();
    }


    /**
     * Create new Treatment in the database.
     * 
     * @param array $data
     * @return \App\Models\Treatment
     */
    public function create(array $data)
    {
        return Treatment::create($data);
    }


    /**
     * Update an existing Treatment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function update(array $data, $id)
    {
        $record = Treatment::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Treatment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Treatment::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Treatment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function find($id)
    {
        return Treatment::find($id);
    }


    /**
     * Find an existing Treatment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Treatment
     */
    public function findByAttribute($attr, $value)
    {
        return Treatment::where($attr, $value)->first();
    }
}
