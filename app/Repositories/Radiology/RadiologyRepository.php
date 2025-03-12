<?php

namespace App\Repositories\Radiology;

use App\Models\Radiology;

class RadiologyRepository implements RadiologyInterface
{
    /**
     * Retrieve a collection of Radiology from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Radiology::all();
    }


    /**
     * Create new Radiology in the database.
     * 
     * @param array $data
     * @return \App\Models\Radiology
     */
    public function create(array $data)
    {
        return Radiology::create($data);
    }


    /**
     * Update an existing Radiology in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function update(array $data, $id)
    {
        $record = Radiology::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Radiology from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Radiology::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Radiology in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function find($id)
    {
        return Radiology::find($id);
    }


    /**
     * Find an existing Radiology in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Radiology
     */
    public function findByAttribute($attr, $value)
    {
        return Radiology::where($attr, $value)->first();
    }
}
