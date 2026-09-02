<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Welcomes a newly registered patient to the hospital and hands them the
 * credentials the patient mobile app expects, along with the store links.
 */
class PatientInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $hospitalName;
    public ?string $password;
    public ?string $patientNo;

    /**
     * @param array{name:string,email:string,hospital_name:string,password:?string,patient_no:?string} $maildata
     */
    public function __construct(array $maildata)
    {
        $this->name         = $maildata['name'];
        $this->email        = $maildata['email'];
        $this->hospitalName = $maildata['hospital_name'];
        $this->password     = $maildata['password'] ?? null;
        $this->patientNo    = $maildata['patient_no'] ?? null;
    }

    public function build()
    {
        return $this->subject("Welcome to {$this->hospitalName} — Your " . config('patient_app.name') . " Account")
            ->view('emails.patient-invitation')
            ->with([
                'name'         => $this->name,
                'email'        => $this->email,
                'hospitalName' => $this->hospitalName,
                'password'     => $this->password,
                'patientNo'    => $this->patientNo,
                'appName'      => config('patient_app.name'),
                'androidUrl'   => config('patient_app.stores.android'),
                'iosUrl'       => config('patient_app.stores.ios'),
                'androidBadge' => config('patient_app.badges.android'),
                'iosBadge'     => config('patient_app.badges.ios'),
                'supportEmail' => config('patient_app.support_email'),
            ]);
    }
}
