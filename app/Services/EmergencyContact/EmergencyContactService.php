<?php

namespace App\Services\EmergencyContact;

use App\Repositories\EmergencyContact\EmergencyContactInterface;

/**
 * Class EmergencyContactService
 * 
 * This class provides services related to EmergencyContact operations and acts as a 
 * layer between the Controller and the EmergencyContactRepository.
 */
class EmergencyContactService
{
    protected EmergencyContactInterface $EmergencyContactInterface;
    /**
     * EmergencyContact constructor.
     * 
     * @param EmergencyContactInterface $EmergencyContactInterface
     */
    public function __construct(EmergencyContactInterface $EmergencyContactInterface)
    {
        $this->EmergencyContactInterface = $EmergencyContactInterface;
    }

    /**
     * Retrieve all EmergencyContact.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->EmergencyContactInterface->all();
    }

    /**
     * Create a new EmergencyContact using the data provided.
     * 
     * @param array $data
     * @return \App\Models\EmergencyContact
     */
    public function create(array $data)
    {
        return $this->EmergencyContactInterface->create($data);
    }


    /**
     * Update an existing EmergencyContact with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function update(array $data, $id)
    {
        return $this->EmergencyContactInterface->update($data, $id);
    }


    /**
     * Delete a EmergencyContact by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->EmergencyContactInterface->delete($id);
    }


    /**
     * Find a EmergencyContact by their ID.
     * 
     * @param int $id
     * @return \App\Models\EmergencyContact
     */
    public function find($id)
    {
        return $this->EmergencyContactInterface->find($id);
    }


    /**
     * Find an existing EmergencyContact  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\EmergencyContact
     */
    public function findByAttribute($attr, $value)
    {
        return $this->EmergencyContactInterface->findByAttribute($attr, $value);
    }
}
