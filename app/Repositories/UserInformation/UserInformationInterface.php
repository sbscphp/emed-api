<?php

namespace App\Repositories\UserInformation;

/**
 * Interface UserInformationInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the UserInformation model.
 */
interface UserInformationInterface
{
    /**
     * Retrieve all UserInformation from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new UserInformation in the database.
     * 
     * @param array $data
     * @return \App\Models\UserInformation
     */
    public function create(array $data);


    /**
     * Update an existing UserInformation in the database.
     * 
     * @param array $data
     * @param int $id
     * @param array $uniqueField
     * @return \App\Models\UserInformation
     */
    public function update(array $data, $id, $uniqueField);


    /**
     * Delete an existing UserInformation from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing UserInformation in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\UserInformation
     */
    public function find($id);


    /**
     * Find an existing UserInformation in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\UserInformation
     */
    public function findByAttribute($attr, $value);

    public function getRegInfoByCustomer($customerId);
}
