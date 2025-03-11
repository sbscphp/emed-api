<?php

namespace App\Services\Admission;

use App\Repositories\Admission\AdmissionInterface;

/**
 * Class AdmissionService
 * 
 * This class provides services related to Admission operations and acts as a 
 * layer between the Controller and the AdmissionRepository.
 */
class AdmissionService
{
    protected AdmissionInterface $AdmissionInterface;
    /**
     * Admission constructor.
     * 
     * @param AdmissionInterface $AdmissionInterface
     */
    public function __construct(AdmissionInterface $AdmissionInterface)
    {
        $this->AdmissionInterface = $AdmissionInterface;
    }

    /**
     * Retrieve all Admission.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->AdmissionInterface->all();
    }

    /**
     * Create a new Admission using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Admission
     */
    public function create(array $data)
    {
        return $this->AdmissionInterface->create($data);
    }


    /**
     * Update an existing Admission with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Admission
     */
    public function update(array $data, $id)
    {
        return $this->AdmissionInterface->update($data, $id);
    }


    /**
     * Delete a Admission by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->AdmissionInterface->delete($id);
    }


    /**
     * Find a Admission by their ID.
     * 
     * @param int $id
     * @return \App\Models\Admission
     */
    public function find($id)
    {
        return $this->AdmissionInterface->find($id);
    }


    /**
     * Find an existing Admission  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Admission
     */
    public function findByAttribute($attr, $value)
    {
        return $this->AdmissionInterface->findByAttribute($attr, $value);
    }
}
