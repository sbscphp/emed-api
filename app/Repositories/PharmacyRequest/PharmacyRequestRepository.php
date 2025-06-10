<?php

namespace App\Repositories\PharmacyRequest;

use App\Models\PharmacyRequest;

class PharmacyRequestRepository implements PharmacyRequestInterface
{
    /**
     * Retrieve a collection of PharmacyRequest from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return PharmacyRequest::all();
    }


    /**
     * Create new PharmacyRequest in the database.
     * 
     * @param array $data
     * @return \App\Models\PharmacyRequest
     */
    public function create(array $data)
    {
        return PharmacyRequest::create($data);
    }


    /**
     * Update an existing PharmacyRequest in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function update(array $data, $id)
    {
        $record = PharmacyRequest::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing PharmacyRequest from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = PharmacyRequest::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing PharmacyRequest in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function find($id)
    {
        return PharmacyRequest::find($id);
    }


    /**
     * Find an existing PharmacyRequest in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PharmacyRequest
     */
    public function findByAttribute($attr, $value)
    {
        return PharmacyRequest::where($attr, $value)->first();
    }
}
