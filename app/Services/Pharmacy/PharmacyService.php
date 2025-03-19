<?php

namespace App\Services\Pharmacy;

use App\Repositories\Pharmacy\PharmacyInterface;

/**
 * Class PharmacyService
 * 
 * This class provides services related to Pharmacy operations and acts as a 
 * layer between the Controller and the PharmacyRepository.
 */
class PharmacyService
{
    protected PharmacyInterface $PharmacyInterface;
    /**
     * Pharmacy constructor.
     * 
     * @param PharmacyInterface $PharmacyInterface
     */
    public function __construct(PharmacyInterface $PharmacyInterface)
    {
        $this->PharmacyInterface = $PharmacyInterface;
    }

    /**
     * Retrieve all Pharmacy.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PharmacyInterface->all();
    }

    /**
     * Create a new Pharmacy using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Pharmacy
     */
    public function create(array $data)
    {
        return $this->PharmacyInterface->create($data);
    }


    /**
     * Update an existing Pharmacy with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function update(array $data, $id)
    {
        return $this->PharmacyInterface->update($data, $id);
    }


    /**
     * Delete a Pharmacy by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PharmacyInterface->delete($id);
    }


    /**
     * Find a Pharmacy by their ID.
     * 
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function find($id)
    {
        return $this->PharmacyInterface->find($id);
    }


    /**
     * Find an existing Pharmacy  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Pharmacy
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PharmacyInterface->findByAttribute($attr, $value);
    }
}
