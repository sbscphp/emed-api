<?php

namespace App\Services\Patient;

use App\Mail\TenantEmailVerification;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Authentication for mobile-app patients (email + password), on the tenant-scoped
 * `patient` JWT guard. Password resets use the tenant `password_reset_tokens` table
 * (leaving `user_id` null — that FK points at staff `users`, not patients).
 */
class PatientAuthService
{
    public function login(string $email, string $password): array
    {
        $token = auth('patient')->attempt(['email' => $email, 'password' => $password]);

        if (!$token) {
            throw new \RuntimeException('Invalid email or password.');
        }

        /** @var Patient $patient */
        $patient = auth('patient')->user();
        $patient->forceFill(['last_login_at' => now()])->save();

        return ['patient' => $patient, 'token' => $token];
    }

    public function changePassword(Patient $patient, string $current, string $new): void
    {
        if (!$patient->password || !Hash::check($current, $patient->password)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $patient->forceFill(['password' => Hash::make($new)])->save();
    }

    /**
     * Email a 6-digit reset OTP. Silent when the email is unknown so we never leak
     * which addresses have accounts.
     */
    public function sendResetOtp(string $email): void
    {
        $patient = Patient::where('email', $email)->first();
        if (!$patient) {
            return;
        }

        $otp = (string) random_int(100000, 999999);

        DB::connection('tenant')->table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => Hash::make($otp),
                'otp'        => $otp,
                'status'     => 'pending',
                'user_id'    => null,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(30),
            ],
        );

        try {
            Mail::to($email)->send(new TenantEmailVerification([
                'email' => $email,
                'name'  => trim($patient->firstname . ' ' . $patient->lastname),
                'token' => $otp,
            ]));
        } catch (\Throwable $th) {
            Log::error('Failed to send patient password-reset OTP.', [
                'email'     => $email,
                'exception' => $th->getMessage(),
            ]);
        }
    }

    public function resetPassword(string $email, string $otp, string $newPassword): void
    {
        $record = DB::connection('tenant')->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($otp, $record->token)) {
            throw new \RuntimeException('Invalid or expired reset code.');
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            throw new \RuntimeException('Invalid or expired reset code.');
        }

        $patient = Patient::where('email', $email)->firstOrFail();
        $patient->forceFill(['password' => Hash::make($newPassword)])->save();

        DB::connection('tenant')->table('password_reset_tokens')->where('email', $email)->delete();
    }
}
