<?php

namespace App\Repositories\Pharmacy;

/**
 * Interface PharmacyInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Pharmacy model.
 */
interface PharmacyInterface
{
    /**
     * Retrieve all Pharmacy from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();
    public function treatmentLogall($search, $from,  $to);
    public function getPatientTreatmentDetails(int $patientId);
    public function fulfillPrescription($data);


    /**
     * Create new Pharmacy in the database.
     * 
     * @param array $data
     * @return \App\Models\Pharmacy
     */
    public function create(array $data);


    /**
     * Update an existing Pharmacy in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Pharmacy from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Pharmacy in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function find($id);


    /**
     * Find an existing Pharmacy in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Pharmacy
     */
    public function findByAttribute($attr, $value);
}
