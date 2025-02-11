<?php

namespace App\Repositories\UserInformation;

use App\Models\UserInformation;

class UserInformationRepository implements UserInformationInterface
{
    /**
     * Retrieve a collection of UserInformation from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return UserInformation::all();
    }


    /**
     * Create new UserInformation in the database.
     * 
     * @param array $data
     * @return \App\Models\UserInformation
     */
    public function create(array $data)
    {
        return UserInformation::create($data);
    }


    /**
     * Update an existing UserInformation in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\UserInformation
     */
    public function update(array $data, $id = null, $uniqueField = [])
    {
        if (empty($uniqueField) && !$id) {
            abort(500, 'id and uniqueField parameters are required');
        }

        $record = UserInformation::query();
        if (!empty($uniqueField)) {
            $record->where($uniqueField[0], $uniqueField[1]);
        } else {
            $record->where('id', $id);
        }
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing UserInformation from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = UserInformation::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing UserInformation in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\UserInformation
     */
    public function find($id)
    {
        return UserInformation::find($id);
    }


    /**
     * Find an existing UserInformation in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\UserInformation
     */
    public function findByAttribute($attr, $value)
    {
        return UserInformation::where($attr, $value)->first();
    }

    public function getRegInfoByCustomer($customerId)
    {
        return UserInformation::where('user_id', $customerId)->first();
    }
}
