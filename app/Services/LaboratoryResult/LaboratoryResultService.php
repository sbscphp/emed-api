<?php

namespace App\Services\LaboratoryResult;

use App\Repositories\LaboratoryResult\LaboratoryResultInterface;

/**
 * Class LaboratoryResultService
 * 
 * This class provides services related to LaboratoryResult operations and acts as a 
 * layer between the Controller and the LaboratoryResultRepository.
 */
class LaboratoryResultService
{
    protected LaboratoryResultInterface $LaboratoryResultInterface;
    /**
     * LaboratoryResult constructor.
     * 
     * @param LaboratoryResultInterface $LaboratoryResultInterface
     */
    public function __construct(LaboratoryResultInterface $LaboratoryResultInterface)
    {
        $this->LaboratoryResultInterface = $LaboratoryResultInterface;
    }

    /**
     * Retrieve all LaboratoryResult.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->LaboratoryResultInterface->all();
    }

    /**
     * Create a new LaboratoryResult using the data provided.
     * 
     * @param array $data
     * @return \App\Models\LaboratoryResult
     */
    public function create(array $data)
    {
        return $this->LaboratoryResultInterface->create($data);
    }


    /**
     * Update an existing LaboratoryResult with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function update(array $data, $id)
    {
        return $this->LaboratoryResultInterface->update($data, $id);
    }


    /**
     * Delete a LaboratoryResult by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->LaboratoryResultInterface->delete($id);
    }


    /**
     * Find a LaboratoryResult by their ID.
     * 
     * @param int $id
     * @return \App\Models\LaboratoryResult
     */
    public function find($id)
    {
        return $this->LaboratoryResultInterface->find($id);
    }


    /**
     * Find an existing LaboratoryResult  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\LaboratoryResult
     */
    public function findByAttribute($attr, $value)
    {
        return $this->LaboratoryResultInterface->findByAttribute($attr, $value);
    }
}
