<?php

namespace App\Repositories\PharmacyRequest;

/**
 * Interface PharmacyRequestInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the PharmacyRequest model.
 */
interface PharmacyRequestInterface
{
    /**
     * Retrieve all PharmacyRequest from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($search, $from, $to, $paginate);


    /**
     * Create new PharmacyRequest in the database.
     * 
     * @param array $data
     * @return \App\Models\PharmacyRequest
     */
    public function create(array $data);


    /**
     * Update an existing PharmacyRequest in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function update(array $data, $id);


    /**
     * Delete an existing PharmacyRequest from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing PharmacyRequest in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\PharmacyRequest
     */
    public function find($id);


    /**
     * Find an existing PharmacyRequest in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PharmacyRequest
     */
    public function findByAttribute($attr, $value);
}
