<?php

use App\Http\Controllers\v1\Admin\AuditLogController;
use App\Http\Controllers\v1\Admin\BillingController;
use App\Http\Controllers\v1\Admin\MedicationInventoryController;
use App\Http\Controllers\v1\Admin\ConsultationController;
use App\Http\Controllers\v1\Admin\LabController;
use App\Http\Controllers\v1\Admin\MedicationController;
use App\Http\Controllers\v1\Admin\PharmacyController;
use App\Http\Controllers\v1\Admin\RecordManagementController;
use App\Http\Controllers\v1\Admin\RegistrationController;
use App\Http\Controllers\v1\Admin\ReportController;
use App\Http\Controllers\v1\Admin\RoleController;
use App\Http\Controllers\v1\Admin\TriageController;
use App\Http\Controllers\v1\Admin\UserController;
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
        Route::group(['middleware' => ["tenant"]], function () {
            Route::group(['prefix' => 'admin', "namespace" => "v1\Admin"], function () {
                //Record routes
                Route::group(['prefix' => 'record',  'middleware' => 'role.record'], function () {
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
                    Route::post('/export/{format}', [RecordManagementController::class, 'exportPatients']);
                });

                Route::group(['prefix' => 'nurse', 'middleware' => 'role.nurse'], function () {
                    Route::post('/triage/{patientId}', [TriageController::class, 'store']);
                    Route::get('/single-triage/{patientId}', [TriageController::class, 'show']);
                    Route::get('/all-records', [TriageController::class, 'getPatientsByService']);
                    Route::get('/investigation-order', [TriageController::class, 'getInvestigationOrders']);
                    Route::post('/export/{format}', [TriageController::class, 'exportTriagePatients']);
                });

                //Consultant routes
                Route::group(['prefix' => 'consultant',  'middleware' => 'role.consultant'], function () {
                    Route::post('/patients', [ConsultationController::class, 'patientsForConsultation']);
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

                Route::group(['prefix' => 'pharmacy', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/create', [PharmacyController::class, 'store']);
                    Route::get('/lists', [PharmacyController::class, 'index']);
                    Route::get('/list/{id}', [PharmacyController::class, 'show']);
                    Route::put('/update/{id}', [PharmacyController::class, 'update']);
                    Route::delete('/delete/{id}', [PharmacyController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [PharmacyController::class, 'toggleStatus']);
                    Route::get('/stats', [PharmacyController::class, 'pharmacyDashboardStats']);
                });

                Route::group(['prefix' => 'medicine', 'middleware' => 'role.billing'], function () {
                    Route::post('/create', [MedicationController::class, 'store']);
                    Route::get('/lists', [MedicationController::class, 'index']);
                    Route::get('/list/{id}', [MedicationController::class, 'show']);
                    Route::put('/update/{id}', [MedicationController::class, 'update']);
                    Route::delete('/delete/{id}', [MedicationController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [MedicationController::class, 'changeStatus']);
                    Route::get('/vendors', [MedicationController::class, 'listVendors']);
                });

                Route::group(['prefix' => 'medicine-inventory', 'middleware' => 'role.billing'], function () {
                    Route::get('/', [MedicationInventoryController::class, 'index']);
                    Route::get('/{id}', [MedicationInventoryController::class, 'show']);
                    Route::post('/', [MedicationInventoryController::class, 'store']);
                    Route::patch('/{id}/status', [MedicationInventoryController::class, 'updateStatus']);
                    Route::get('/dashboard/stats', [MedicationInventoryController::class, 'shipmentStat']);
                });

                Route::group(['prefix' => 'billing', 'middleware' => 'role.billing'], function () {
                    Route::get('/', [BillingController::class, 'index']);
                    Route::post('/', [BillingController::class, 'store']);
                    Route::get('/{id}', [BillingController::class, 'show']);
                    Route::put('/{id}', [BillingController::class, 'update']);
                    Route::delete('/{id}', [BillingController::class, 'destroy']);
                    Route::get('/dashboard/stats', [BillingController::class, 'getBillingStatistics']);
                    Route::get('/all/services', [BillingController::class, 'getAllServiceUnitsAndTypes']);
                    Route::get('/service-unit/{id}', [BillingController::class, 'getBillingByServiceUnit']);
                    Route::get('/service-type/all', [BillingController::class, 'getBillingByServiceType']);
                });

                Route::group(['prefix' => 'report', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/dashboard/stats', [ReportController::class, 'getReportStatistics']);
                    Route::get('/', [ReportController::class, 'index']);
                    Route::get('/patient', [ReportController::class, 'getPatientReport']);
                    Route::get('/financial', [ReportController::class, 'getFinancialReport']);
                    Route::get('/system', [ReportController::class, 'getAllSystemReport']);
                });

                //Laboratory Routes
                Route::prefix('laboratory')->group(function () {
                    Route::get('/stats', [LabController::class, 'stats']);
                    Route::post('/records', [LabController::class, 'allLabRecords']);
                    Route::get('/single-lab-record/{id}', [LabController::class, 'show']);
                });

                //Audit Log Routes
                Route::group(['prefix' => 'auditLog', 'middleware' => 'admin.superadmin'], function () {
                    Route::post('/logs', [AuditLogController::class, 'userActivity']);
                    Route::get('/logs-download/{type}', [AuditLogController::class, 'downloadAuditLog']);
                });

                //Roles Routes
                Route::group(['prefix' => 'role', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/all', [RoleController::class, 'index']);
                    Route::post('/create', [RoleController::class, 'store']);
                    Route::get('/view/{id}', [RoleController::class, 'show']);
                    Route::put('/update/{id}', [RoleController::class, 'update']);
                });

                //Users Routes
                Route::group(['prefix' => 'users', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/all', [UserController::class, 'allUsers']);
                    Route::post('/create', [UserController::class, 'addUser']);
                    Route::get('/view/{id}', [UserController::class, 'viewUser']);
                    Route::put('/update/{id}', [UserController::class, 'updateUser']);
                    Route::delete('/delete/{id}', [UserController::class, 'deleteUser']);
                });
            });
        });
    });
});
