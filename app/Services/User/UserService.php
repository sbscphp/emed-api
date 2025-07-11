<?php

namespace App\Services\User;

use App\Http\Resources\AuditResources;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\New_State;
use App\Models\Region;
use App\Models\Subregions;
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

    public function fetch_country_state_city($request)
    {
        $city_name = $request->get('city_name');
        $state_name = $request->get('state_name');
        $country_name = $request->get('country_name');

        $data = City::with(['state', 'country'])
            ->when($city_name, function ($query, $city_name) {
                $query->where('cities.name', $city_name);
            })
            ->when($state_name, function ($query, $state_name) {
                $query->whereHas('state', function ($q) use ($state_name) {
                    $q->where('name', $state_name);
                });
            })
            ->when($country_name, function ($query, $country_name) {
                $query->whereHas('country', function ($q) use ($country_name) {
                    $q->where('name', $country_name);
                });
            })
            ->limit(100)
            ->get();

        return $data;
    }

    public function user_activity($validate)
    {
        //AuditLog  AuditLogTransaction
        // user_id action_type
        $auditlog = AuditLog::with(['audit_log_transactions', 'causer'])->when(!empty($validate['user_id']) && !empty($validate['action_type']), function ($query) use ($validate) {
            $query->where("user_id", $validate['user_id'])
                ->orWhere('action_type', $validate['action_type']);
        })->paginate($validate['limit'] ?? 10);

        if ($validate['is_download']) {

            if ($validate['export'] == 'csv') {
                $data =  AuditLog::with('audit_log_transactions')->when(!empty($validate['user_id']) && !empty($validate['action_type']), function ($query) use ($validate) {
                    $query->where("user_id", $validate['user_id'])
                        ->orWhere('action_type', $validate['action_type']);
                })->get();

                $convertdata = AuditResources::collection($data)->resolve();
            } else if ($validate['export'] == 'pdf') {

                $data =  AuditLog::with('audit_log_transactions')->when(!empty($validate['user_id']) && !empty($validate['action_type']), function ($query) use ($validate) {
                    $query->where("user_id", $validate['user_id'])
                        ->orWhere('action_type', $validate['action_type']);
                })->get();

                $convertdata = AuditResources::collection($data)->resolve();
            }
        }

        return $auditlog;
    }
}
