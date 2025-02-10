<?php

namespace App\Services\User;

use App\Repositories\User\UserRepositoryInterface;

/**
 * Class UserService
 * 
 * This class provides services related to User operations and acts as a 
 * layer between the controller and the UserRepository.
 */
class UserService
{
    protected UserRepositoryInterface $userRepositoryInterface;
    /**
     * UserService constructor.
     * 
     * @param UserRepositoryInterface $userRepositoryInterface
     */
    public function __construct(UserRepositoryInterface $userRepositoryInterface)
    {
        $this->userRepositoryInterface = $userRepositoryInterface;
    }

    /**
     * Create a new user using the data provided.
     * 
     * @param array $data
     * @return \App\Models\User
     */
    public function create(array $data)
    {
        return $this->userRepositoryInterface->create($data);
    }

    /**
     * Update an existing user with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\User
     */
    public function update(array $data, $id)
    {
        return $this->userRepositoryInterface->update($data, $id);
    }

    /**
     * Delete a user by their ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->userRepositoryInterface->delete($id);
    }

    /**
     * Retrieve all users.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->userRepositoryInterface->all();
    }

    /**
     * Find a user by their ID.
     * 
     * @param int $id
     * @param array $selectAttrs
     * @return \App\Models\User
     */
    public function find(int $id, array $selectAttrs = [])
    {
        return $this->userRepositoryInterface->find($id, $selectAttrs);
    }

    /**
     * Find a user by phone number.
     * 
     * @param int $phone_number
     * @return \App\Models\User
     */
    public function findByPhoneNumber($phone_number)
    {
        return $this->userRepositoryInterface->findByPhoneNumber($phone_number);
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
        return $this->userRepositoryInterface->findByAttribute($attr, $value);
    }
}
