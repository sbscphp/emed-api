<?php

namespace App\Services\PatientVisit;

use App\Repositories\PatientVisit\PatientVisitInterface;

/**
 * Class PatientVisitService
 * 
 * This class provides services related to PatientVisit operations and acts as a 
 * layer between the Controller and the PatientVisitRepository.
 */
class PatientVisitService
{
    protected PatientVisitInterface $PatientVisitInterface;
    /**
     * PatientVisit constructor.
     * 
     * @param PatientVisitInterface $PatientVisitInterface
     */
    public function __construct(PatientVisitInterface $PatientVisitInterface)
    {
        $this->PatientVisitInterface = $PatientVisitInterface;
    }

    /**
     * Retrieve all PatientVisit.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PatientVisitInterface->all();
    }

    /**
     * Create a new PatientVisit using the data provided.
     * 
     * @param array $data
     * @return \App\Models\PatientVisit
     */
    public function create(array $data)
    {
        return $this->PatientVisitInterface->create($data);
    }


    /**
     * Update an existing PatientVisit with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function update(array $data, $id)
    {
        return $this->PatientVisitInterface->update($data, $id);
    }


    /**
     * Delete a PatientVisit by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PatientVisitInterface->delete($id);
    }


    /**
     * Find a PatientVisit by their ID.
     * 
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function find($id)
    {
        return $this->PatientVisitInterface->find($id);
    }


    /**
     * Find an existing PatientVisit  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientVisit
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PatientVisitInterface->findByAttribute($attr, $value);
    }
}
