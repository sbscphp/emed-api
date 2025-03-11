<?php

namespace App\Services\PatientInformation;

use App\Repositories\PatientInformation\PatientInformationInterface;

/**
 * Class PatientInformationService
 * 
 * This class provides services related to PatientInformation operations and acts as a 
 * layer between the Controller and the PatientInformationRepository.
 */
class PatientInformationService
{
    protected PatientInformationInterface $PatientInformationInterface;
    /**
     * PatientInformation constructor.
     * 
     * @param PatientInformationInterface $PatientInformationInterface
     */
    public function __construct(PatientInformationInterface $PatientInformationInterface)
    {
        $this->PatientInformationInterface = $PatientInformationInterface;
    }

    /**
     * Retrieve all PatientInformation.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PatientInformationInterface->all();
    }

    /**
     * Create a new PatientInformation using the data provided.
     * 
     * @param array $data
     * @return \App\Models\PatientInformation
     */
    public function create(array $data)
    {
        return $this->PatientInformationInterface->create($data);
    }


    /**
     * Update an existing PatientInformation with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function update(array $data, $id)
    {
        return $this->PatientInformationInterface->update($data, $id);
    }


    /**
     * Delete a PatientInformation by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PatientInformationInterface->delete($id);
    }


    /**
     * Find a PatientInformation by their ID.
     * 
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function find($id)
    {
        return $this->PatientInformationInterface->find($id);
    }


    /**
     * Find an existing PatientInformation  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientInformation
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PatientInformationInterface->findByAttribute($attr, $value);
    }
}
