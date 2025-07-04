<?php

namespace App\Services\PharmacyRequest;

use App\Repositories\PharmacyRequest\PharmacyRequestInterface;

/**
 * Class PharmacyRequestService
 * 
 * This class provides services related to PharmacyRequest operations and acts as a 
 * layer between the Controller and the PharmacyRequestRepository.
 */
class PharmacyRequestService
{
    protected PharmacyRequestInterface $PharmacyRequestInterface;
    /**
     * PharmacyRequest constructor.
     * 
     * @param PharmacyRequestInterface $PharmacyRequestInterface
     */
    public function __construct(PharmacyRequestInterface $PharmacyRequestInterface)
    {
        $this->PharmacyRequestInterface = $PharmacyRequestInterface;
    }

    /**
     * Retrieve all PharmacyRequest.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($search, $from, $to)
    {
        return $this->PharmacyRequestInterface->all($search, $from, $to);
    }

    /**
     * Create a new PharmacyRequest using the data provided.
     * 
     * @param array $data
     * @return \App\Models\PharmacyRequest
     */
    public function create(array $data)
    {
        return $this->PharmacyRequestInterface->create($data);
    }


    /**
     * Update an existing PharmacyRequest with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function update(array $data, $id)
    {
        return $this->PharmacyRequestInterface->update($data, $id);
    }


    /**
     * Delete a PharmacyRequest by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PharmacyRequestInterface->delete($id);
    }


    /**
     * Find a PharmacyRequest by their ID.
     * 
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function find($id)
    {
        return $this->PharmacyRequestInterface->find($id);
    }


    /**
     * Find an existing PharmacyRequest  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PharmacyRequest
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PharmacyRequestInterface->findByAttribute($attr, $value);
    }
}
