<?php

namespace App\Repositories\Role;

/**
 * Interface RoleInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Role model.
 */
interface RoleInterface
{
    /**
     * Retrieve all Role from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Role in the database.
     * 
     * @param array $data
     * @return \App\Models\Role
     */
    public function create(array $data);


    /**
     * Update an existing Role in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Role
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Role from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Role in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Role
     */
    public function find($id);


    /**
     * Find an existing Role in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Role
     */
    public function findByAttribute($attr, $value);
}
