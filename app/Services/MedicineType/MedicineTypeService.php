<?php

namespace App\Services\MedicineType;

use App\Repositories\MedicineType\MedicineTypeInterface;

/**
 * Class MedicineTypeService
 * 
 * This class provides services related to MedicineType operations and acts as a 
 * layer between the Controller and the MedicineTypeRepository.
 */
class MedicineTypeService
{
    protected MedicineTypeInterface $MedicineTypeInterface;
    /**
     * MedicineType constructor.
     * 
     * @param MedicineTypeInterface $MedicineTypeInterface
     */
    public function __construct(MedicineTypeInterface $MedicineTypeInterface)
    {
        $this->MedicineTypeInterface = $MedicineTypeInterface;
    }

    /**
     * Retrieve all MedicineType.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($filters, $request)
    {
        return $this->MedicineTypeInterface->all($filters, $request);
    }

    /**
     * Create a new MedicineType using the data provided.
     * 
     * @param array $data
     * @return \App\Models\MedicineType
     */
    public function create(array $data)
    {
        return $this->MedicineTypeInterface->create($data);
    }


    /**
     * Update an existing MedicineType with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function update(array $data, $id)
    {
        return $this->MedicineTypeInterface->update($data, $id);
    }


    /**
     * Delete a MedicineType by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->MedicineTypeInterface->delete($id);
    }


    /**
     * Find a MedicineType by their ID.
     * 
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function find($id)
    {
        return $this->MedicineTypeInterface->find($id);
    }


    /**
     * Find an existing MedicineType  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\MedicineType
     */
    public function findByAttribute($attr, $value)
    {
        return $this->MedicineTypeInterface->findByAttribute($attr, $value);
    }
}
