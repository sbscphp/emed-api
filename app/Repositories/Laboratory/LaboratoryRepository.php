<?php

namespace App\Repositories\Laboratory;

use App\Models\Laboratory;

class LaboratoryRepository implements LaboratoryInterface
{
    /**
     * Retrieve a collection of Laboratory from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Laboratory::all();
    }


    /**
     * Create new Laboratory in the database.
     * 
     * @param array $data
     * @return \App\Models\Laboratory
     */
    public function create(array $data)
    {
        return Laboratory::create($data);
    }


    /**
     * Update an existing Laboratory in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function update(array $data, $id)
    {
        $record = Laboratory::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Laboratory from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Laboratory::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Laboratory in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function find($id)
    {
        return Laboratory::find($id);
    }


    /**
     * Find an existing Laboratory in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Laboratory
     */
    public function findByAttribute($attr, $value)
    {
        return Laboratory::where($attr, $value)->first();
    }
}
