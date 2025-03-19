<?php

namespace App\Repositories\PatientVisit;

/**
 * Interface PatientVisitInterface
 *
 * This interface defines the methods that must be implemented by any
 * class that handles the data operations for the PatientVisit model.
 */
interface PatientVisitInterface
{
    /**
     * Retrieve all PatientVisit from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new PatientVisit in the database.
     *
     * @param array $data
     * @return \App\Models\PatientVisit
     */
    public function create(array $data);


    /**
     * Update an existing PatientVisit in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function update(array $data, $id);


    /**
     * Delete an existing PatientVisit from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing PatientVisit in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function find($id);


    /**
     * Find an existing PatientVisit in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientVisit
     */
    public function findByAttribute($attr, $value);

    public function findByMultiAttributes(array $attrs);

    public function getPatientForConsultationToday();
    public function getPatientVisits($patientId);
    public function getPatientPreviousVisits($patientId, $visitNo);

}
