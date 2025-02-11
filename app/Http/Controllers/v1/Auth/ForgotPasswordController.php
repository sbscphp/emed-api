<?php

namespace App\Http\Controllers\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordLinkRequest;
use App\Mail\PasswordResetEmail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Responser\JsonResponser;
use App\Services\User\UserService;

class ForgotPasswordController extends Controller
{
    protected UserService $userInterface;

    public function __construct(UserService $userInterface)
    {
        $this->userInterface = $userInterface;
    }

    public function resetPasswordLink(ResetPasswordLinkRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = $this->userInterface->findByAttribute('email', $request->email);

            if (!$user) {
                return JsonResponser::send(true, 'Email address not found.', [], 400);
            }

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            $email = $request->email;
            $verification_code = Str::random(64); // Generate a secure verification code
            $otpCode = random_int(100000, 999999); // Generate a 6-digit OTP

            $record = DB::table('password_reset_tokens')->insertGetId([
                'user_id' => $user->id,
                'otp' => $otpCode,
                'email' => $email,
                'token' => $verification_code,
                'created_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

            $data = [
                'name' => $user->fullname,
                'email' => $email,
                'verification_code' => $verification_code,
                'subject' => "Reset Password Notification",
                'otp' => $otpCode,
            ];

            Mail::to($email)->send(new PasswordResetEmail($data));

            DB::commit();
            return JsonResponser::send(false, 'Password reset link sent to the email associated with your account.', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error during password reset request: ' . $th->getMessage());
            return JsonResponser::send(true, 'An error occurred while processing your request.', [], 500, $th);
        }
    }
}
