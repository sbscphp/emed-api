<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\BiometricRequest;
use App\Http\Requests\Patient\CreatePasswordRequest;
use App\Http\Requests\Patient\FindHospitalsRequest;
use App\Http\Requests\Patient\PatientChangePasswordRequest;
use App\Http\Requests\Patient\PatientLoginRequest;
use App\Http\Requests\Patient\RequestPasswordResetRequest;
use App\Http\Requests\Patient\ResetPasswordRequest;
use App\Http\Requests\Patient\VerifyInvitationRequest;
use App\Http\Requests\Patient\VerifyPasswordResetOtpRequest;
use App\Responser\JsonResponser;
use App\Services\Patient\PatientAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Onboarding and sign in for the patient mobile app.
 *
 * Everything here is scoped to one hospital chosen by the caller, because a
 * patient can be registered by several of them with the same account.
 */
class PatientAuthController extends Controller
{
    public function __construct(protected PatientAuthService $patientAuthService) {}

    /**
     * POST /v1/patient/hospitals
     *
     * The hospitals that know this email address or phone number, for the
     * pickers on the verification and login screens. Only the patient's own
     * hospitals are returned, never the full directory.
     */
    public function hospitals(FindHospitalsRequest $request)
    {
        try {
            $hospitals = $this->patientAuthService->hospitals($request->identifier);

            if ($hospitals->isEmpty()) {
                return JsonResponser::send(true, 'No hospital is associated with the details you entered.', [], 404);
            }

            return JsonResponser::send(false, 'Record found.', $hospitals, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/verify-invitation
     *
     * Answers with a `status` of invitation_found, account_exists or not_found,
     * which is the screen the app should show next. A found invitation also
     * verifies the patient's email address and returns the token the create
     * password screen needs.
     */
    public function verifyInvitation(VerifyInvitationRequest $request)
    {
        try {
            $result = $this->patientAuthService->verifyInvitation(
                $request->identifier,
                $request->hospital_uuid
            );

            $message = match ($result['status']) {
                PatientAuthService::STATUS_INVITATION_FOUND => 'Invitation found. Please confirm your details.',
                PatientAuthService::STATUS_ACCOUNT_EXISTS   => 'You already have an account. Please sign in.',
                default => 'We could not find an invitation using the information you entered.',
            };

            return JsonResponser::send(false, $message, $result, 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/create-password
     *
     * Spends the verification token and signs the patient straight in, so the
     * app can move on to the biometric prompt.
     */
    public function createPassword(CreatePasswordRequest $request)
    {
        try {
            $result = $this->patientAuthService->createPassword($request->token, $request->password);

            return JsonResponser::send(false, 'Your password has been created successfully.', $result, 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/login
     */
    public function login(PatientLoginRequest $request)
    {
        try {
            $result = $this->patientAuthService->login(
                $request->identifier,
                $request->password,
                $request->hospital_uuid
            );

            return JsonResponser::send(false, 'Login successful.', $result, 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/request-reset-password
     *
     * Mails a 6 digit code to the address on the account.
     */
    public function requestPasswordReset(RequestPasswordResetRequest $request)
    {
        try {
            $result = $this->patientAuthService->requestPasswordResetOtp($request->identifier);

            return JsonResponser::send(false, 'We have sent a reset code to the email on your account.', $result, 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/verify-reset-otp
     *
     * Exchanges a valid code for the token the reset screen spends.
     */
    public function verifyPasswordResetOtp(VerifyPasswordResetOtpRequest $request)
    {
        try {
            $result = $this->patientAuthService->verifyPasswordResetOtp($request->identifier, $request->otp);

            return JsonResponser::send(false, 'Code verified. You can now set a new password.', $result, 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $this->patientAuthService->resetPasswordWithToken($request->reset_token, $request->password);

            return JsonResponser::send(false, 'Your password has been reset successfully. Please sign in.', [], 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/biometric
     *
     * "Enable" / "Skip for Now" on the biometric screen shown after the first
     * sign in, and the same switch in settings afterwards.
     */
    public function biometric(BiometricRequest $request)
    {
        try {
            $user = $this->patientAuthService->setBiometric(Auth::user(), $request->boolean('enabled'));

            $message = $user->biometric_enabled
                ? 'Biometric sign in enabled.'
                : 'Biometric sign in disabled.';

            return JsonResponser::send(false, $message, [
                'biometric_enabled' => (bool) $user->biometric_enabled,
            ], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/change-password
     */
    public function changePassword(PatientChangePasswordRequest $request)
    {
        try {
            $this->patientAuthService->changePassword(
                Auth::user(),
                $request->current_password,
                $request->password
            );

            return JsonResponser::send(false, 'Your password has been changed successfully.', [], 200);
        } catch (PatientAuthException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/hospitals/mine
     *
     * The hospitals a signed in patient attends, for the hospital switcher.
     */
    public function myHospitals()
    {
        try {
            $hospitals = $this->patientAuthService->hospitalsForUser(Auth::user());

            return JsonResponser::send(false, 'Hospitals retrieved successfully.', $hospitals, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/auth/logout
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return JsonResponser::send(false, 'You have been logged out successfully.', [], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
