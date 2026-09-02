<?php

namespace App\Http\Controllers\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordLinkRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\PasswordResetEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Hash;

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

            // Force central DB
            $user = User::on('landlord')->where('email', $request->email)->first();

            if (!$user) {
                return JsonResponser::send(true, 'Email address not found.', [], 400);
            }

            DB::connection('landlord')->table('password_reset_tokens')->where('email', $request->email)->delete();

            $email = $request->email;
            $verification_code = Str::random(64); // Secure token
            $otpCode = random_int(100000, 999999); // 6-digit OTP

            $record = DB::connection('landlord')->table('password_reset_tokens')->insertGetId([
                'user_id'   => $user->id,
                'email'     => $email,
                'otp'       => $otpCode,
                'token'     => $verification_code,
                'created_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(1),
            ]);

            $data = [
                'name'              => $user->first_name . ' ' . $user->last_name,
                'email'             => $email,
                'verification_code' => $verification_code,
                'subject'           => "Reset Password Notification",
                'otp'               => $otpCode,
                'url'               => $request->password_url . '?token=' . $verification_code . '&email=' . urlencode($email),
            ];

            Mail::to($email)->send(new PasswordResetEmail($data));

            DB::commit();
            return JsonResponser::send(false, 'Password reset link sent to the email associated with your account.', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'An error occurred while processing your request.', [], 500, $th);
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $validatedData = $request->validated();

            $resetToken = DB::table('password_reset_tokens')
                ->where('token', $validatedData['token'])
                ->where('email', $validatedData['email'])
                ->where('expires_at', '>', now())
                ->where('status', 'pending')
                ->first();

            if (!$resetToken) {
                return JsonResponser::send(true, 'Invalid or expired token.', [], 400);
            }

            $user = User::where('email', $validatedData['email'])->first();

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', [], 204);
            }

            $user->password = Hash::make($validatedData['password']);
            // Choosing a password through a reset is still choosing one, so the
            // temporary password prompt has to clear here too. Left set, the
            // patient app would keep offering "create password" to someone who
            // has already replaced the credentials we mailed them.
            $user->must_change_password = 0;
            $user->save();

            DB::connection('landlord')->table('password_reset_tokens')
                ->where('token', $validatedData['token'])
                ->where('email', $validatedData['email'])
                ->delete();

            return JsonResponser::send(false, 'Your password has been reset successfully.', [], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while resetting your password.', [], 500, $th);
        }
    }
}
