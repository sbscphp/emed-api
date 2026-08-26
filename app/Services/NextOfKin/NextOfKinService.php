<?php

namespace App\Services\NextOfKin;

use App\Repositories\NextOfKin\NextOfKinInterface;

/**
 * Class NextOfKinService
 * 
 * This class provides services related to NextOfKin operations and acts as a 
 * layer between the Controller and the NextOfKinRepository.
 */
class NextOfKinService
{
    protected NextOfKinInterface $NextOfKinInterface;
    /**
     * NextOfKin constructor.
     * 
     * @param NextOfKinInterface $NextOfKinInterface
     */
    public function __construct(NextOfKinInterface $NextOfKinInterface)
    {
        $this->NextOfKinInterface = $NextOfKinInterface;
    }

    /**
     * Retrieve all NextOfKin.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->NextOfKinInterface->all();
    }

    /**
     * Create a new NextOfKin using the data provided.
     * 
     * @param array $data
     * @return \App\Models\NextOfKin
     */
    public function create(array $data)
    {
        return $this->NextOfKinInterface->create($data);
    }


    /**
     * Update an existing NextOfKin with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function update($data, $id)
    {
    }


    /**
     * Delete a NextOfKin by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->NextOfKinInterface->delete($id);
    }


    /**
     * Find a NextOfKin by their ID.
     * 
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function find($id)
    {
        return $this->NextOfKinInterface->find($id);
    }


    /**
     * Find an existing NextOfKin  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\NextOfKin
     */
    public function findByAttribute($attr, $value)
    {
        return $this->NextOfKinInterface->findByAttribute($attr, $value);
    }
}
