<?php

namespace App\Repositories\MedicineType;

/**
 * Interface MedicineTypeInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the MedicineType model.
 */
interface MedicineTypeInterface
{
    /**
     * Retrieve all MedicineType from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($filters, $request);


    /**
     * Create new MedicineType in the database.
     * 
     * @param array $data
     * @return \App\Models\MedicineType
     */
    public function create(array $data);


    /**
     * Update an existing MedicineType in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function update(array $data, $id);


    /**
     * Delete an existing MedicineType from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing MedicineType in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function find($id);


    /**
     * Find an existing MedicineType in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\MedicineType
     */
    public function findByAttribute($attr, $value);
}
