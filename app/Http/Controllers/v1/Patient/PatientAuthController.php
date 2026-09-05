<?php

namespace App\Http\Controllers\v1\Patient;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Patient\PatientAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientAuthController extends Controller
{
    public function __construct(private PatientAuthService $authService) {}

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $result = $this->authService->login($request->email, $request->password);

            return JsonResponser::send(false, 'Login successful.', [
                'patient'     => $result['patient'],
                'accessToken' => $result['token'],
                'tokenType'   => 'Bearer',
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 400);
        }
    }

    public function me()
    {
        return JsonResponser::send(false, 'Patient profile', auth('patient')->user());
    }

    public function logout()
    {
        try {
            auth('patient')->logout();

            return JsonResponser::send(false, 'Logged out successfully', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Unable to log out.', [], 500, $th);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $this->authService->changePassword(auth('patient')->user(), $request->current_password, $request->new_password);

            return JsonResponser::send(false, 'Password changed successfully', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        $this->authService->sendResetOtp($request->email);

        // Always the same response so account existence is not leaked.
        return JsonResponser::send(false, 'If an account exists for that email, a reset code has been sent.', []);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => ['required', 'email'],
            'otp'          => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $this->authService->resetPassword($request->email, $request->otp, $request->new_password);

            return JsonResponser::send(false, 'Password reset successfully. You can now log in.', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 400);
        }
    }
}
