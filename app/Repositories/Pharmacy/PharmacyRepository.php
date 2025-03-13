<?php

namespace App\Repositories\Pharmacy;

use App\Models\Pharmacy;

class PharmacyRepository implements PharmacyInterface
{
    /**
     * Retrieve a collection of Pharmacy from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Pharmacy::with('state:id,state_name')->get();
    }


    /**
     * Create new Pharmacy in the database.
     * 
     * @param array $data
     * @return \App\Models\Pharmacy
     */
    public function create(array $data)
    {
        return Pharmacy::create($data);
    }


    /**
     * Update an existing Pharmacy in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function update(array $data, $id)
    {
        $record = Pharmacy::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Pharmacy from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Pharmacy::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Pharmacy in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function find($id)
    {
        return Pharmacy::with('state:id,state_name')->find($id);
    }



    /**
     * Find an existing Pharmacy in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Pharmacy
     */
    public function findByAttribute($attr, $value)
    {
        return Pharmacy::where($attr, $value)->first();
    }
}
