<?php

use App\Http\Controllers\v1\Admin\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/verify-email/{token}', [RegistrationController::class, 'verifyEmail'])->name('verify.email');
