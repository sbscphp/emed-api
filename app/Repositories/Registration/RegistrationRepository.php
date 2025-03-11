<?php

namespace App\Repositories\Registration;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RegistrationRepository implements RegistrationInterface
{
    /**
     * Retrieve a collection of Registration from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Registration::all();
    }


    /**
     * Create new Registration in the database.
     * 
     * @param array $data
     * @return \App\Models\Registration
     */
    public function create(array $data)
    {
        return Registration::create($data);
    }


    /**
     * Update an existing Registration in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Registration
     */
    public function update(array $data, $id)
    {
        $record = Registration::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Registration from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Registration::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Registration in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Registration
     */
    public function find($id)
    {
        return Registration::find($id);
    }


    /**
     * Find an existing Registration in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Registration
     */
    public function findByAttribute($attr, $value)
    {
        return Registration::where($attr, $value)->first();
    }
    /**
     * Save or update hospital details using updateOrCreate.
     */
    public function saveHospitalDetails(array $data)
    {
        return Registration::updateOrCreate(
            ['email' => $data['email']],
            [
                'name'                => $data['name'],
                'state_city'          => $data['state_city'],
                'registration_number' => $data['registration_number'],
                'email'               => $data['email'],
                'phone_number'        => $data['phone_number'],
                'address'             => $data['address'],
                'license'             => $data['license'] ?? null,
            ]
        );
    }

    /**
     * Save or update admin user details for the hospital.
     */
    public function saveAdminDetails(array $data, int $tenantId)
    {
        return User::updateOrCreate(
            ['email' => $data['email']],
            [
                'fullname'    => $data['fullname'],
                'role'         => $data['role'],
                'phone_number' => $data['phone_number'],
                'password'     => bcrypt($data['password']),
            ]
        );
    }
}
