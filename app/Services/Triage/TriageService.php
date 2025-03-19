<?php

namespace App\Services\Triage;

use App\Repositories\Triage\TriageInterface;

/**
 * Class TriageService
 * 
 * This class provides services related to Triage operations and acts as a 
 * layer between the Controller and the TriageRepository.
 */
class TriageService
{
    protected TriageInterface $TriageInterface;
    /**
     * Triage constructor.
     * 
     * @param TriageInterface $TriageInterface
     */
    public function __construct(TriageInterface $TriageInterface)
    {
        $this->TriageInterface = $TriageInterface;
    }

    /**
     * Retrieve all Triage.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->TriageInterface->all();
    }

    /**
     * Create a new Triage using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Triage
     */
    public function create(array $data)
    {
        return $this->TriageInterface->create($data);
    }

    public function updateOrCreate(array $conditions, array $data)
    {
        return $this->TriageInterface->updateOrCreate($conditions, $data);
    }

    /**
     * Update an existing Triage with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Triage
     */
    public function update(array $data, $id)
    {
        return $this->TriageInterface->update($data, $id);
    }


    /**
     * Delete a Triage by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->TriageInterface->delete($id);
    }


    /**
     * Find a Triage by their ID.
     * 
     * @param int $id
     * @return \App\Models\Triage
     */
    public function find($id)
    {
        return $this->TriageInterface->find($id);
    }


    /**
     * Find an existing Triage  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Triage
     */
    public function findByAttribute($attr, $value)
    {
        return $this->TriageInterface->findByAttribute($attr, $value);
    }

    public function getTriageByPatient(int $patientId)
    {
        return $this->TriageInterface->getByPatientId($patientId);
    }
}
