<?php

namespace App\Services\Radiology;

use App\Repositories\Radiology\RadiologyInterface;

/**
 * Class RadiologyService
 * 
 * This class provides services related to Radiology operations and acts as a 
 * layer between the Controller and the RadiologyRepository.
 */
class RadiologyService
{
    protected RadiologyInterface $RadiologyInterface;
    /**
     * Radiology constructor.
     * 
     * @param RadiologyInterface $RadiologyInterface
     */
    public function __construct(RadiologyInterface $RadiologyInterface)
    {
        $this->RadiologyInterface = $RadiologyInterface;
    }

    /**
     * Retrieve all Radiology.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->RadiologyInterface->all();
    }

    /**
     * Create a new Radiology using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Radiology
     */
    public function create(array $data)
    {
        return $this->RadiologyInterface->create($data);
    }


    /**
     * Update an existing Radiology with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function update(array $data, $id)
    {
        return $this->RadiologyInterface->update($data, $id);
    }


    /**
     * Delete a Radiology by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->RadiologyInterface->delete($id);
    }


    /**
     * Find a Radiology by their ID.
     * 
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function find($id)
    {
        return $this->RadiologyInterface->find($id);
    }


    /**
     * Find an existing Radiology  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Radiology
     */
    public function findByAttribute($attr, $value)
    {
        return $this->RadiologyInterface->findByAttribute($attr, $value);
    }
}
