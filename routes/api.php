<?php

use App\Http\Controllers\v1\Admin\RecordManagementController;
use App\Http\Controllers\v1\Admin\RegistrationController;
use App\Http\Controllers\v1\Auth\ForgotPasswordController;
use App\Http\Controllers\v1\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


Route::group(["prefix" => "v1"], function () {

    /** Cache **/
    Route::get('/clear-cache', function () {
        Artisan::call('optimize:clear');
        return "Data Cache is cleared";
    });

    Route::group(['prefix' => 'auth', "namespace" => "v1\Auth"], function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/request-reset-password', [ForgotPasswordController::class, 'resetPasswordLink']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('logout', [LoginController::class, 'logout']);
        });
    });

    Route::group(['prefix' => 'admin'], function () {
        Route::post('/login', [RegistrationController::class, 'adminLogin']);
    });
    Route::group(["middleware" => ["auth:api"]], function () {
        Route::group(['prefix' => 'admin'], function () {
            Route::post('/register', [RegistrationController::class, 'onboardTenant']);
        });
    });
    Route::group(['prefix' => 'admin', 'middleware' => ["tenant"]], function () {
        Route::post('/register-patient', [RecordManagementController::class, 'storePatient']);
        // Route::get('/clear-cache-auth', function () {
        //     Artisan::call('optimize:clear');
        //     return "Data Cache is cleared";
        // });

    });
});
