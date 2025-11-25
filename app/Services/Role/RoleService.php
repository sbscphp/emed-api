<?php

namespace App\Services\Role;

use App\Models\Role;
use App\Repositories\Role\RoleInterface;

/**
 * Class RoleService
 * 
 * This class provides services related to Role operations and acts as a 
 * layer between the Controller and the RoleRepository.
 */
class RoleService
{
    protected RoleInterface $RoleInterface;
    /**
     * Role constructor.
     * 
     * @param RoleInterface $RoleInterface
     */
    public function __construct(RoleInterface $RoleInterface)
    {
        $this->RoleInterface = $RoleInterface;
    }

    /**
     * Retrieve all Role.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($tenantId, $request)
    {
        return $this->RoleInterface->all($tenantId, $request);
    }

    /**
     * Create a new Role using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Role
     */
    public function create(array $data)
    {
        return $this->RoleInterface->create($data);
    }


    /**
     * Update an existing Role with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Role
     */
    public function update(array $data, $id)
    {
        return $this->RoleInterface->update($data, $id);
    }


    /**
     * Delete a Role by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->RoleInterface->delete($id);
    }


    /**
     * Find a Role by their ID.
     * 
     * @param int $id
     * @return \App\Models\Role
     */
    public function find($id)
    {
        return $this->RoleInterface->find($id);
    }


    /**
     * Find an existing Role  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Role
     */
    public function findByAttribute($attr, $value)
    {
        return $this->RoleInterface->findByAttribute($attr, $value);
    }

    public function getAdminRole(): Role
    {
        return Role::where('name', 'admin')->firstOrFail();
    }
}
