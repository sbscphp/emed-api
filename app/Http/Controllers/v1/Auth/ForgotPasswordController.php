<?php

namespace App\Http\Controllers\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordLinkRequest;
use App\Mail\PasswordResetEmail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\User\UserService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Responser\JsonResponser;

class ForgotPasswordController extends Controller
{
    protected UserService $userService;

    public function __construct(
        UserService $userService,
    ) {
        $this->userService = $userService;
    }

    public function resetPasswordLink(ResetPasswordLinkRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = $this->userService->findByAttribute('email', $request->email);

            if (!$user) {
                return JsonResponser::send(true, 'Email address not found.', [], 400);
            }

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            $email = $request->email;
            $verification_code = Str::random(30); //Generate verification code
            $otpCode = random_int(10000, 99999); //generate random num

            $record = DB::table('password_reset_tokens')->insert([
                'user_id' => $user->id, 
                'otp' => $otpCode, 
                'email' => $email, 
                'token' => $verification_code, 
                'created_at' => Carbon::now(), 
                'expires_at' => Carbon::now()->addMinutes(10)
            ]);

            $data = [
                'name' => $user->firstname,
                'email' => $email,
                'verification_code' => $verification_code,
                'subject' => "Reset Password Notification",
            ];

            Mail::to("akinwunmi.damilola@yahoo.com")->send(new PasswordResetEmail($data));

            DB::commit();
            return JsonResponser::send(false, 'Password reset link sent to the email associated with your account.', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }
}
