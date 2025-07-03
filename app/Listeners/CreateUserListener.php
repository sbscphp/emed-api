<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\CreateUserEvent;
use App\Mail\SendUserAccount;
use Illuminate\Support\Facades\Mail;
class CreateUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CreateUserEvent $event): void
    {
    
        $data = [
            'fullname'=>$event->fullname,
            'email'=>$event->email,
            'password'=>$event->password
        ];

     Mail::to($event->email)->send( new SendUserAccount($data) );
    }
}
