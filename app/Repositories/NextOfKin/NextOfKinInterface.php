<?php

namespace App\Repositories\NextOfKin;

/**
 * Interface NextOfKinInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the NextOfKin model.
 */
interface NextOfKinInterface
{
    /**
     * Retrieve all NextOfKin from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new NextOfKin in the database.
     * 
     * @param array $data
     * @return \App\Models\NextOfKin
     */
    public function create(array $data);


    /**
     * Update an existing NextOfKin in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function update(array $data, $id);


    /**
     * Delete an existing NextOfKin from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing NextOfKin in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function find($id);


    /**
     * Find an existing NextOfKin in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\NextOfKin
     */
    public function findByAttribute($attr, $value);
}
