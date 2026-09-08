<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the one time code a patient types to prove they own the address
 * before choosing a new password.
 */
class PatientPasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $otp;
    public int $expiresInMinutes;

    /**
     * @param array{name:string,email:string,otp:string|int,expires_in_minutes:int} $maildata
     */
    public function __construct(array $maildata)
    {
        $this->name             = $maildata['name'];
        $this->email            = $maildata['email'];
        $this->otp              = (string) $maildata['otp'];
        $this->expiresInMinutes = (int) $maildata['expires_in_minutes'];
    }

    public function build()
    {
        return $this->subject('Your ' . config('patient_app.name') . ' Password Reset Code')
            ->view('emails.patient-password-reset-otp')
            ->with([
                'name'             => $this->name,
                'email'            => $this->email,
                'otp'              => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
                'appName'          => config('patient_app.name'),
                'supportEmail'     => config('patient_app.support_email'),
            ]);
    }
}
