<?php

namespace App\Repositories\PatientInformation;

/**
 * Interface PatientInformationInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the PatientInformation model.
 */
interface PatientInformationInterface
{
    /**
     * Retrieve all PatientInformation from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new PatientInformation in the database.
     * 
     * @param array $data
     * @return \App\Models\PatientInformation
     */
    public function create(array $data);


    /**
     * Update an existing PatientInformation in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function update(array $data, $id);


    /**
     * Delete an existing PatientInformation from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing PatientInformation in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function find($id);


    /**
     * Find an existing PatientInformation in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientInformation
     */
    public function findByAttribute($attr, $value);
}
