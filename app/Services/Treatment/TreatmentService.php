<?php

namespace App\Services\Treatment;

use App\Repositories\Treatment\TreatmentInterface;

/**
 * Class TreatmentService
 * 
 * This class provides services related to Treatment operations and acts as a 
 * layer between the Controller and the TreatmentRepository.
 */
class TreatmentService
{
    protected TreatmentInterface $TreatmentInterface;
    /**
     * Treatment constructor.
     * 
     * @param TreatmentInterface $TreatmentInterface
     */
    public function __construct(TreatmentInterface $TreatmentInterface)
    {
        $this->TreatmentInterface = $TreatmentInterface;
    }

    /**
     * Retrieve all Treatment.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->TreatmentInterface->all();
    }

    /**
     * Create a new Treatment using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Treatment
     */
    public function create(array $data)
    {
        return $this->TreatmentInterface->create($data);
    }


    /**
     * Update an existing Treatment with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function update(array $data, $id)
    {
        return $this->TreatmentInterface->update($data, $id);
    }


    /**
     * Delete a Treatment by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->TreatmentInterface->delete($id);
    }


    /**
     * Find a Treatment by their ID.
     * 
     * @param int $id
     * @return \App\Models\Treatment
     */
    public function find($id)
    {
        return $this->TreatmentInterface->find($id);
    }


    /**
     * Find an existing Treatment  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Treatment
     */
    public function findByAttribute($attr, $value)
    {
        return $this->TreatmentInterface->findByAttribute($attr, $value);
    }
}
