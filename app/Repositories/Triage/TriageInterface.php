<?php

namespace App\Repositories\Triage;

use App\Models\Triage;

/**
 * Interface TriageInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the Triage model.
 */
interface TriageInterface
{
    /**
     * Retrieve all Triage from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Triage in the database.
     * 
     * @param array $data
     * @return \App\Models\Triage
     */
    public function create(array $data): Triage;

    /**
     * Update an existing Triage in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Triage
     */
    public function update(array $data, $id);

    public function updateOrCreate(array $conditions, array $data);

    /**
     * Delete an existing Triage from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Triage in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Triage
     */
    public function find($id);


    /**
     * Find an existing Triage in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Triage
     */
    public function findByAttribute($attr, $value);
    public function getByPatientId(int $patientId);
}
