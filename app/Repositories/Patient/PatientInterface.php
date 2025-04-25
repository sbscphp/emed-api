<?php

namespace App\Repositories\Patient;

use Illuminate\Http\Request;

/**
 * Interface PatientInterface
 *
 * This interface defines the methods that must be implemented by any
 * class that handles the data operations for the Patient model.
 */
interface PatientInterface
{
    /**
     * Retrieve all Patient from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Patient in the database.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create(array $data);


    /**
     * Update an existing Patient in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Patient from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Patient in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Patient
     */
    public function find($id);


    /**
     * Find an existing Patient in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Patient
     */
    public function findByAttribute($attr, $value);

    public function findByMultiAttributes(array $attrs);

    public function findMultipleRecordsByMultiAttributes(array $attrs);

    public function findUserByFirstnameAndLastname($firstname, $lastname);

    /**
     * Retrieve all records
     *
     * @return \App\Models\Patient
     */
    public function getAllRecords($search, $paginate, $perPage);

    /**
     * Retrieve record stats
     *
     * @return \App\Models\Patient
     */
    public function getRecordStats();
    public function getPatientReport(Request $request);
}
