<?php

namespace App\Services\User;

use App\Http\Resources\AuditResources;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\New_State;
use App\Models\Region;
use App\Models\Subregions;
use App\Helpers\ExportHelper;
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


    public  function generateSecurePassword(): string
    {
        // Define the required character sets
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()-_=+<>?';

        // Combine all character sets into one
        $allChars = $lowercase . $uppercase . $numbers . $specialChars;

        // Create a password with required criteria
        $password = '';
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $specialChars[rand(0, strlen($specialChars) - 1)];

        // Fill the remaining characters randomly from the combined set
        $remainingLength = rand(4, 16); // Remaining length to meet min and max limits (8 to 20)
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }

        // Shuffle the characters in the password to ensure random distribution
        return str_shuffle($password);
    }

    /**
     * Build a temporary password that satisfies the patient app's password
     * rules (minimum 8 characters, upper, lower, number, special character)
     * while staying readable enough to be retyped from an email.
     *
     * Characters that are easily confused when read off a screen (O/0, l/1, …)
     * are left out on purpose, and the characters are drawn with random_int so
     * the password cannot be guessed the way generateRoleBasedPassword() can.
     */
    public function generateTemporaryPassword(int $length = 12): string
    {
        $lowercase = 'abcdefghijkmnpqrstuvwxyz';
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '23456789';
        $specialChars = '@#$%&*!?';

        $length = max(8, $length);
        $allChars = $lowercase . $uppercase . $numbers . $specialChars;

        // Seed one character from every set so the result always passes the
        // rules, then fill the rest at random.
        $characters = [
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $specialChars[random_int(0, strlen($specialChars) - 1)],
        ];

        for ($i = count($characters); $i < $length; $i++) {
            $characters[] = $allChars[random_int(0, strlen($allChars) - 1)];
        }

        shuffle($characters);

        return implode('', $characters);
    }

    public function generateRoleBasedPassword(string $roleName, string $firstName, string $lastName): string
    {
        // Get initials from first and last name
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

        // Define some symbols
        $symbols = ['@', '#', '!', '$', '&'];

        // Pick random symbol and number
        $symbol = $symbols[array_rand($symbols)];
        $number = rand(10, 999);

        // Clean and capitalize role name (remove spaces like "Lab Technician" → "LabTechnician")
        $roleSegment = ucfirst(str_replace(' ', '', $roleName));

        // Build final password
        return "{$symbol}{$roleSegment}{$initials}{$number}";
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
    public function all($filters, $search, $export, $paginate, $perPage)
    {
        return $this->userRepositoryInterface->all($filters, $search, $export, $paginate, $perPage);
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

    public function getSystemReport($request)
    {
        return $this->userRepositoryInterface->getSystemReport($request);
    }

    public function user_activity($validate)
    {
        //AuditLog  AuditLogTransaction
        // user_id action_type
        $auditlog = AuditLog::with(['audit_log_transactions', 'causer' => function ($query) use ($validate) {
            if (!empty($validate['name'])) {
                $query->where('fullname', $validate['name']);
            }

            if (!empty($validate['status'])) {
                $query->where('status', $validate['status']);
            }
        }, 'causer.userInformation'])->when(!empty($validate['action_type']), function ($query) use ($validate) {
            //$query->where("user_id", $validate['user_id'])
            $query->where('action_type', $validate['action_type']);
        })->paginate($validate['limit'] ?? 10);



        return $auditlog;
    }
}
