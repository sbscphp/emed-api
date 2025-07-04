<?php

namespace App\Repositories\Inventory;

/**
 * Interface InventoryInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Inventory model.
 */
interface InventoryInterface
{
    /**
     * Retrieve all Inventory from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();

    public function getAllWithFilters(array $filters = [], ?string $export = null, $from, $to);
    /**
     * Create new Inventory in the database.
     * 
     * @param array $data
     * @return \App\Models\Inventory
     */
    public function create(array $data);


    /**
     * Update an existing Inventory in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Inventory from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Inventory in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function find($id);


    /**
     * Find an existing Inventory in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Inventory
     */
    public function findByAttribute($attr, $value);
}
