<?php

namespace App\Services\Registration;

use App\Models\User;
use Illuminate\Support\Str;
use App\Repositories\Registration\RegistrationInterface;

/**
 * Class RegistrationService
 * 
 * This class provides services related to Registration operations and acts as a 
 * layer between the Controller and the RegistrationRepository.
 */
class RegistrationService
{
    protected RegistrationInterface $registrationInterface;
    /**
     * Registration constructor.
     * 
     * @param RegistrationInterface $registrationInterface
     */
    public function __construct(RegistrationInterface $registrationInterface)
    {
        $this->registrationInterface = $registrationInterface;
    }

    /**
     * Retrieve all Registration.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->registrationInterface->all();
    }

    /**
     * Create a new Registration using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Registration
     */
    public function create(array $data)
    {
        return $this->registrationInterface->create($data);
    }


    /**
     * Update an existing Registration with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Registration
     */
    public function update(array $data, $id)
    {
        return $this->registrationInterface->update($data, $id);
    }


    /**
     * Delete a Registration by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->registrationInterface->delete($id);
    }


    /**
     * Find a Registration by their ID.
     * 
     * @param int $id
     * @return \App\Models\Registration
     */
    public function find($id)
    {
        return $this->registrationInterface->find($id);
    }


    /**
     * Find an existing Registration  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Registration
     */
    public function findByAttribute($attr, $value)
    {
        return $this->registrationInterface->findByAttribute($attr, $value);
    }


    public function saveHospitalDetails(array $criteria, array $data)
    {
        return \App\Models\Registration::updateOrCreate($criteria, $data);
    }

    public function saveAdminDetails(array $data, int $tenantId)
    {
        return User::updateOrCreate(
            ['email' => $data['email']],
            [
                'uuid' => Str::uuid(),
                'fullname'    => $data['fullname'],
                'role'         => $data['role'],
                'phone_number' => $data['phone_number'],
                'password'     => bcrypt($data['password']),
                'tenant_id'    => $tenantId,
            ]
        );
    }
}
