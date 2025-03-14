<?php

namespace App\Repositories\Consultation;

/**
 * Interface ConsultationInterface
 *
 * This interface defines the methods that must be implemented by any
 * class that handles the data operations for the Consultation model.
 */
interface ConsultationInterface
{
    /**
     * Retrieve all Consultation from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Consultation in the database.
     *
     * @param array $data
     * @return \App\Models\Consultation
     */
    public function create(array $data);


    /**
     * Update an existing Consultation in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Consultation from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Consultation in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function find($id);


    /**
     * Find an existing Consultation in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Consultation
     */
    public function findByAttribute($attr, $value);

    public function getPatients();

    public function findByVisitNoLabOrBoth($visitno);
    public function findByVisitNoRadiologyOrBoth($visitno);
}
