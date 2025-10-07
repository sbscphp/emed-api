<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $name;
    public $token;

    /**
     * Create a new message instance.
     */
    public function __construct(array $maildata)
    {
        $this->email = $maildata['email'];
        $this->name = $maildata['name'];
        $this->token = $maildata['token'];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Verify Your Email Address')
            ->view('emails.tenant_verification')
            ->with([
                'name'  => $this->name,
                'email' => $this->email,
                'token' => $this->token,
            ]);
    }
}
