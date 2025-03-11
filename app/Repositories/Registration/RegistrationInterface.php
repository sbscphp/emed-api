<?php

namespace App\Repositories\Registration;

/**
 * Interface RegistrationInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Registration model.
 */
interface RegistrationInterface
{
    /**
     * Retrieve all Registration from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Registration in the database.
     * 
     * @param array $data
     * @return \App\Models\Registration
     */
    public function create(array $data);


    /**
     * Update an existing Registration in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Registration
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Registration from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Registration in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Registration
     */
    public function find($id);


    /**
     * Find an existing Registration in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Registration
     */
    public function findByAttribute($attr, $value);

    /**
     * Save or update hospital (tenant) details.
     *
     * @param array $data
     * @return \App\Models\Registration
     */
    public function saveHospitalDetails(array $data);

    /**
     * Save or update admin user details for a tenant.
     *
     * @param array $data
     * @param int $tenantId
     * @return \App\Models\User
     */
    public function saveAdminDetails(array $data, int $tenantId);

    /**
     * Finalize onboarding steps for the tenant.
     *
     * @param int $tenantId
     * @return \App\Models\Registration
     */
}
