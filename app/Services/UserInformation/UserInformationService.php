<?php

namespace App\Services\UserInformation;

use App\Repositories\UserInformation\UserInformationInterface;

/**
 * Class UserInformationService
 * 
 * This class provides services related to UserInformation operations and acts as a 
 * layer between the controller and the UserInformationRepository.
 */
class UserInformationService
{
    protected UserInformationInterface $UserInformationInterface;
    /**
     * UserInformation constructor.
     * 
     * @param UserInformationInterface $UserInformationInterface
     */
    public function __construct(UserInformationInterface $UserInformationInterface)
    {
        $this->UserInformationInterface = $UserInformationInterface;
    }

    /**
     * Retrieve all UserInformation.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->UserInformationInterface->all();
    }

    /**
     * Create a new UserInformation using the data provided.
     * 
     * @param array $data
     * @return \App\Models\UserInformation
     */
    public function create(array $data)
    {
        return $this->UserInformationInterface->create($data);
    }


    /**
     * Update an existing UserInformation with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @param array $uniqueField
     * @return \App\Models\UserInformation
     */
    public function update(array $data, int|null $id, array $uniqueField)
    {
        return $this->UserInformationInterface->update($data, $id, $uniqueField);
    }


    /**
     * Delete a UserInformation by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->UserInformationInterface->delete($id);
    }


    /**
     * Find a UserInformation by their ID.
     * 
     * @param int $id
     * @return \App\Models\UserInformation
     */
    public function find($id)
    {
        return $this->UserInformationInterface->find($id);
    }


    /**
     * Find an existing UserInformation  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\UserInformation
     */
    public function findByAttribute($attr, $value)
    {
        return $this->UserInformationInterface->findByAttribute($attr, $value);
    }
}
