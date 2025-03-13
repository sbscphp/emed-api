<?php

use App\Http\Controllers\v1\Admin\ConsultationController;
use App\Http\Controllers\v1\Admin\RecordManagementController;
use App\Http\Controllers\v1\Admin\RegistrationController;
use App\Http\Controllers\v1\Admin\TriageController;
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

    Route::group(['prefix' => 'admin'], function () {
        Route::post('/register', [RegistrationController::class, 'onboardTenant']);
    });

    Route::group(["middleware" => ["auth:api"]], function () {
        // Route::group(['prefix' => 'admin'], function () {
        //     Route::post('/register', [RegistrationController::class, 'onboardTenant']);

        // });

        Route::group(['middleware' => ["tenant"]], function () {
            Route::group(['prefix' => 'admin', "namespace" => "v1\Admin"], function () {
                //Record routes
                Route::group(['prefix' => 'record'], function () {
                    Route::post('/patient', [RecordManagementController::class, 'store']);
                    Route::put('/patient-update/{id}', [RecordManagementController::class, 'update']);
                    Route::post('/next-of-kin/{id}', [RecordManagementController::class, 'addNextOfKin']);
                    Route::put('/next-of-kin-update/{id}', [RecordManagementController::class, 'updateNextOfKin']);
                    Route::post('/emergency-contact/{id}', [RecordManagementController::class, 'addEmergencyContact']);
                    Route::put('/emergency-contact-update/{id}', [RecordManagementController::class, 'updateNextOfKin']);
                    Route::put('/assign-patient/{id}', [RecordManagementController::class, 'assignServiceToPatient']);
                    Route::get('/patient/{id}', [RecordManagementController::class, 'show'])->name('record.show');
                    Route::post('/all-records', [RecordManagementController::class, 'allRecords']);
                    Route::post('/initiate-visit/{id}', [RecordManagementController::class, 'initiateVisit']);
                    Route::get('/record-stats', [RecordManagementController::class, 'recordStats']);
                });

                //Consultant routes
                Route::group(['prefix' => 'consultant'], function () {
                    Route::get('/patients', [ConsultationController::class, 'patientsForConsultation']);
                    Route::post('/patient/{id}', [ConsultationController::class, 'storeConsultationInfo']);
                });
            });
        });
    });
});
