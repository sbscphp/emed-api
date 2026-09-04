<?php

use App\Http\Controllers\v1\Admin\RegistrationController;
use App\Http\Controllers\Web\PaymentSupportPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/verify-email/{token}', [RegistrationController::class, 'verifyEmail'])->name('verify.email');

/*
|--------------------------------------------------------------------------
| Shared Payment Links
|--------------------------------------------------------------------------
|
| The page a patient's support link opens, and the only browser facing part of
| the billing module. Web rather than API because the person following the link
| is a friend of the patient with no account, no app and no token — the token in
| the URL is the whole of what identifies the request.
|
| The path is the tail of the configured support URL, which is why it is
| /payment-support here and per environment in .env:
|
|     local     http://127.0.0.1:8000/payment-support/{token}
|     staging   https://emed.quick-retail.com/payment-support/{token}
|     live      https://emeddiaries.com/payment-support/{token}
|
| Throttled like its API counterpart, because an unauthenticated route that
| resolves a token is worth guessing at.
|
*/
Route::prefix('payment-support')->name('payment-support.')->middleware('throttle:30,1')->group(function () {
    Route::get('/{token}', [PaymentSupportPageController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{16,64}')
        ->name('show');

    Route::post('/{token}/contribute', [PaymentSupportPageController::class, 'contribute'])
        ->where('token', '[A-Za-z0-9]{16,64}')
        ->name('contribute');

    // Where Paystack returns the supporter. Declared after the show route but
    // matched before it, because its path carries an extra segment.
    Route::get('/{token}/callback', [PaymentSupportPageController::class, 'callback'])
        ->where('token', '[A-Za-z0-9]{16,64}')
        ->name('callback');
});
