<?php

namespace App\Repositories\Vendor;

/**
 * Interface VendorInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Vendor model.
 */
interface VendorInterface
{
    /**
     * Retrieve all Vendor from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all(array $filters = [], ?string $export = null);


    /**
     * Create new Vendor in the database.
     * 
     * @param array $data
     * @return \App\Models\Vendor
     */
    public function create(array $data);


    /**
     * Update an existing Vendor in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Vendor from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Vendor in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function find($id);


    /**
     * Find an existing Vendor in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Vendor
     */
    public function findByAttribute($attr, $value);

               
    public function update_status($validated, $id);
}
