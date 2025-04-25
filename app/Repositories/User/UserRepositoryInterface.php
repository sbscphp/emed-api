<?php

namespace App\Repositories\User;

use Illuminate\Http\Request;

/**
 * Interface UserRepositoryInterface
 * 
 * This interface defines the methods that must be implemented by any 
 * class that handles the data operations for the User model.
 */
interface UserRepositoryInterface
{
    /**
     * Retrieve all users from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all();

    /**
     * Create a new user in the database.
     * 
     * @param array $data
     * @return \App\Models\User
     */
    public function create(array $data);

    /**
     * Update an existing user in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\User
     */
    public function update(array $data, $id);

    /**
     * Delete a user from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id);

    /**
     * Find a user in the database by their ID.
     * 
     * @param int $id
     * @param array $selectAttr
     * @return \App\Models\User
     */
    public function find(int $id, array $selectAttr = []);


    /**
     * Find a user in the database by phone number.
     * 
     * @param int $phone_number
     * @return \App\Models\User
     */
    public function findByPhoneNumber($phone_number);

    /**
     * Find a user by $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\User
     */
    public function findByAttribute($attr, $value);
    public function getSystemReport(Request $request);
}
