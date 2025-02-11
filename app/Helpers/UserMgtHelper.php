<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserMgtHelper
{

    //Get User id
    public static function userInstance()
    {
        $userInstance = Auth::user();
        return $userInstance;
    }
}
