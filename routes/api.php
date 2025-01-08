<?php

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

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('logout', [LoginController::class, 'logout']);
        });
        

    });

    Route::group(['prefix' => 'admin', 'middleware' => ["tenant", "auth:api"]], function () {
        Route::get('/clear-cache-auth', function () {
            Artisan::call('optimize:clear');
            return "Data Cache is cleared";
        });

    });

});
