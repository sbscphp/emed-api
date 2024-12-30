<?php

namespace App\Http\Controllers\v1\Auth;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{

    protected UserService $userService;

    public function __construct(
        UserService $userService,
    ) {
        $this->userService = $userService;
    }

    /**
     * Handle login and return an API token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Check if the user exists and the password is correct
        $user = $this->userService->findByAttribute('email', $request->email);

        if(!$user){
            return JsonResponser::send(true, "Email not found.", [], 403);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return JsonResponser::send(true, "Invalid password.", [], 403);
        }

        if (!$user->is_verified) {
            return JsonResponser::send(true, 'Account not verified. Kindly verify your email', [], 400);
        }
        
        if (!$user->is_active) {
            return JsonResponser::send(true, 'Your account has been deactivated. Please contact the administrator', [], 400);
        }

        // Issue a new API token
        // $token = $user->createToken(env("APP_NAME"))->plainTextToken;
        $token = $user->createToken(env("APP_NAME"), ['*'], now()->addHours(1))->plainTextToken;

        dd($token);
        dd(auth()->user());

        $data = [
            "user" => $user,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
        ];

        $dataToLog = [
            'causer_id' => $user->id,
            'action_id' => $user['id'],
            'action_type' => "Models\User",
            'log_name' => "User logged in successfully",
            'description' => "{$user['firstname']} {$user['lastname']} logged in successfully",
        ];

        GeneralHelper::storeAuditLog($dataToLog);

        return JsonResponser::send(false, 'You are logged in successfully', $data);
    }
}
