<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CreateUser extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $name;
    public $hospitalName;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct(array $maildata)
    {
        $this->email        = $maildata['email'];
        $this->name         = $maildata['name'];
        $this->hospitalName = $maildata['hospital_name'];
        $this->password     = $maildata['password'];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Welcome to {$this->hospitalName} Hospital Management System")
            ->view('emails.account-created')
            ->with([
                'name'         => $this->name,
                'email'        => $this->email,
                'hospitalName' => $this->hospitalName,
                'password'     => $this->password,
            ]);
    }
}
