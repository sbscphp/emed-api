<?php

use App\Http\Controllers\v1\Admin\ConsultationController;
use App\Http\Controllers\v1\Admin\PharmacyController;
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

                Route::group(['prefix' => 'nurse'], function () {
                    Route::post('/triage/{patientId}', [TriageController::class, 'store']);
                    Route::get('/all-records', [TriageController::class, 'getPatientsByService']);
                    Route::get('/patient-statistics', [TriageController::class, 'getPatientStatistics']);
                });

                //Consultant routes
                Route::group(['prefix' => 'consultant'], function () {
                    Route::get('/patients', [ConsultationController::class, 'patientsForConsultation']);
                    Route::get('/patient/{visitNo}', [ConsultationController::class, 'show']);
                    Route::post('/patient/{visitNo}/store', [ConsultationController::class, 'storeConsultationInfo']);
                    Route::post('/patient/{visitNo}/lab', [ConsultationController::class, 'storeLabInfo']);
                    Route::post('/patient/{visitNo}/radiology', [ConsultationController::class, 'storeRadiologyInfo']);
                    Route::post('/patient/{visitNo}/treatment', [ConsultationController::class, 'storeTreatmentInfo']);
                    Route::post('/patient/medical/{patientId}', [ConsultationController::class, 'storeMedicalHistory']);
                    Route::post('/patient/family/{patientId}', [ConsultationController::class, 'storeFamilyHistory']);
                    Route::post('/patient/social/{patientId}', [ConsultationController::class, 'storeSocialHistory']);
                    Route::post('/patient/drug/{patientId}', [ConsultationController::class, 'storeDrugHistory']);

                });

                Route::prefix('pharmacy')->group(function () {
                    Route::post('/create', [PharmacyController::class, 'store']);
                    Route::get('/lists', [PharmacyController::class, 'index']);
                    Route::get('/list/{id}', [PharmacyController::class, 'show']);
                    Route::put('/update/{id}', [PharmacyController::class, 'update']);
                    Route::delete('/delete/{id}', [PharmacyController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [PharmacyController::class, 'toggleStatus']);
                });
            });
        });
    });
});
