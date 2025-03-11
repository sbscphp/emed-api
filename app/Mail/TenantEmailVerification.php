<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationUrl;
    public $data;

    public function __construct($verificationUrl, $data)
    {
        $this->verificationUrl = $verificationUrl;
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Email Verification Required')
            ->view('emails.tenant_verification')
            ->with([
                'verificationUrl' => $this->verificationUrl,
                'data' => $this->data,
            ]);
    }
}
