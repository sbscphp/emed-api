<?php

namespace App\Repositories\User;

use App\Models\User;

/**
 * Class UserRepository
 * 
 * This class handles the database operations for the User model.
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Retrieve all users from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return User::all();
    }

    /**
     * Create a new user in the database.
     * 
     * @param array $data
     * @return \App\Models\User
     */
    public function create(array $data)
    {
        return User::create($data);
    }

    /**
     * Update an existing user in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\User
     */
    public function update(array $data, $id)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    /**
     * Delete a user from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
    }

    /**
     * Find a user in the database by their ID.
     * 
     * @param int $id
     * @param array $selectAttr
     * @return \App\Models\User
     */
    public function find(int $id, array $selectAttrs = [])
    {
        return User::select($selectAttrs ? $selectAttrs : '*')->find($id);
    }

    /**
     * Find a user in the database by their phone number.
     * 
     * @param string $phone_number
     * @return \App\Models\User
     */
    public function findByPhoneNumber($phone_number)
    {
        return User::where('phoneno', $phone_number)->first();
    }

    /**
     * Find a user by $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\User
     */
    public function findByAttribute($attr, $value)
    {
        return User::where($attr, $value)->first();
    }
}
