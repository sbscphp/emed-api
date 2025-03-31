<?php

namespace App\Repositories\Laboratory;

/**
 * Interface LaboratoryInterface
 *
 * This interface defines the methods that must be implemented by any
 * class that handles the data operations for the Laboratory model.
 */
interface LaboratoryInterface
{
    /**
     * Retrieve all Laboratory from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();


    /**
     * Create new Laboratory in the database.
     *
     * @param array $data
     * @return \App\Models\Laboratory
     */
    public function create(array $data);


    /**
     * Update an existing Laboratory in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function update(array $data, $id);


    /**
     * Delete an existing Laboratory from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id);


    /**
     * Find an existing Laboratory in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function find($id);


    /**
     * Find an existing Laboratory in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Laboratory
     */
    public function findByAttribute($attr, $value);

    public function getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage);
    public function getStats();
}
