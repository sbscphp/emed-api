<?php

namespace App\Repositories\LaboratoryResult;

use App\Models\LaboratoryResult;

class LaboratoryResultRepository implements LaboratoryResultInterface
{
    /**
     * Retrieve a collection of LaboratoryResult from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return LaboratoryResult::all();
    }


    /**
     * Create new LaboratoryResult in the database.
     * 
     * @param array $data
     * @return \App\Models\LaboratoryResult
     */
    public function create(array $data)
    {
        return LaboratoryResult::create($data);
    }


    /**
     * Update an existing LaboratoryResult in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function update(array $data, $id)
    {
        $record = LaboratoryResult::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing LaboratoryResult from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = LaboratoryResult::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing LaboratoryResult in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function find($id)
    {
        return LaboratoryResult::find($id);
    }


    /**
     * Find an existing LaboratoryResult in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\LaboratoryResult
     */
    public function findByAttribute($attr, $value)
    {
        return LaboratoryResult::where($attr, $value)->first();
    }
}
