<?php

use App\Http\Controllers\v1\Admin\AuditLogController;
use App\Http\Controllers\v1\Admin\BillingController;
use App\Http\Controllers\v1\Admin\MedicationInventoryController;
use App\Http\Controllers\v1\Admin\ConsultationController;
use App\Http\Controllers\v1\Admin\InventoryController;
use App\Http\Controllers\v1\Admin\LabController;
use App\Http\Controllers\v1\Admin\MedicationController;
use App\Http\Controllers\v1\Admin\MedicineTypeController;
use App\Http\Controllers\v1\Admin\PharmacyController;
use App\Http\Controllers\v1\Admin\PharmacyRequestController;
use App\Http\Controllers\v1\Admin\PharmacySupplyController;
use App\Http\Controllers\v1\Admin\RecordManagementController;
use App\Http\Controllers\v1\Admin\RegistrationController;
use App\Http\Controllers\v1\Admin\ReportController;
use App\Http\Controllers\v1\Admin\RoleController;
use App\Http\Controllers\v1\Admin\TriageController;
use App\Http\Controllers\v1\Admin\UserController;
use App\Http\Controllers\v1\Admin\VendorController;
use App\Http\Controllers\v1\Auth\ForgotPasswordController;
use App\Http\Controllers\v1\Auth\LoginController;
use Illuminate\Http\Request;
// use App\Http\Controllers\v1\Admin\ArtisanController;
use App\Http\Controllers\v1\Admin\ArtisanController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::group(["prefix" => "v1"], function () {
    /** Cache **/
    // https://emed.sbscuk.co.uk/public/api/v1/clear-cache
    Route::get('/clear-cache', function () {
        Artisan::call('optimize:clear');
        Artisan::call('config:cache');
        Artisan::call('route:clear');

        return "Data Cache is cleared";
    });

    // Route::get('/test_all-records', [RecordManagementController::class, 'allRecords']);

    Route::get('/fetch_country_state_city', [UserController::class, 'fetch_country_state_city']);
    Route::get('/run_migration', [UserController::class, 'run_migration']);
    Route::get('/run_name', [UserController::class, 'run_name']);
    //  Route::post('/update_status/{id}', [VendorController::class, 'update_status']);
    Route::group(['prefix' => 'auth', "namespace" => "v1\Auth"], function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/request-reset-password', [ForgotPasswordController::class, 'resetPasswordLink']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
        // Route::middleware(['auth:sanctum'])->group(function () {
        //     Route::get('logout', [LoginController::class, 'logout']);
        // });
    });

    Route::group(['prefix' => 'admin'], function () {
        Route::post('/login', [RegistrationController::class, 'adminLogin']);
    });

    Route::group(['prefix' => 'admin'], function () {
        Route::post('/register', [RegistrationController::class, 'onboardTenant']);
    });

    Route::group(["middleware" => ["auth:api"]], function () {
        Route::group(['middleware' => ["tenant"]], function () {
            // Route::get('/test_all-records', [RecordManagementController::class, 'allRecords']);


            Route::get('/me', [RegistrationController::class, 'me']);
            Route::get('/check_is_change_password', [RegistrationController::class, 'check_is_change_password']);
            Route::put('/change_password', [RegistrationController::class, 'change_password']);
            Route::get('/refreshToken', [RegistrationController::class, 'refreshToken']);
            Route::post('/logout', [RegistrationController::class, 'logout']);

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
                    Route::post('/visits/all', [RecordManagementController::class, 'allVisitRecords']);
                    Route::post('/patient/visit/{id}', [RecordManagementController::class, 'patientVisitRecords']);
                    Route::get('/patient/{patientId}/visit/{visitId}', [RecordManagementController::class, 'patientVisitDetailWithBilling']);
                });

                Route::group(['prefix' => 'nurse', 'middleware' => 'role.nurse'], function () {
                    // all-records
                    Route::post('/triage/{patientId}', [TriageController::class, 'store']);
                    Route::get('/single-triage/{patientId}', [TriageController::class, 'show']);
                    Route::post('/all-records', [TriageController::class, 'getPatientsByService']);
                    Route::get('/investigation-order', [TriageController::class, 'getInvestigationOrders']);
                    Route::post('/export/{format}', [TriageController::class, 'exportTriagePatients']);
                });

                //Consultant routes
                Route::group(['prefix' => 'consultant',  'middleware' => 'role.consultant'], function () {
                    Route::post('/patients', [ConsultationController::class, 'patientsForConsultation']);
                    Route::get('/patient/{visitNo}', [ConsultationController::class, 'show']);
                    Route::post('/all/patients', [ConsultationController::class, 'getAllVisits']);
                    Route::post('/patient/{visitNo}/store', [ConsultationController::class, 'storeConsultationInfo']);
                    Route::post('/patient/{visitNo}/lab', [ConsultationController::class, 'storeLabInfo']);
                    Route::post('/patient/{visitNo}/radiology', [ConsultationController::class, 'storeRadiologyInfo']);
                    Route::post('/patient/{visitNo}/treatment', [ConsultationController::class, 'storeTreatmentInfo']);
                    Route::post('/patient/medical/{patientId}', [ConsultationController::class, 'storeMedicalHistory']);
                    Route::post('/patient/family/{patientId}', [ConsultationController::class, 'storeFamilyHistory']);
                    Route::post('/patient/social/{patientId}', [ConsultationController::class, 'storeSocialHistory']);
                    Route::post('/patient/drug/{patientId}', [ConsultationController::class, 'storeDrugHistory']);
                    Route::get('/patient_laboratory/{patientId}', [ConsultationController::class, 'patient_laboratory']);
                });

                Route::group(['prefix' => 'pharmacy', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/create', [PharmacyController::class, 'store']);
                    Route::post('/lists', [PharmacyController::class, 'index']);
                    Route::post('/patient/lists', [PharmacyController::class, 'treatmentLogs']);
                    Route::get('/patient/{patientId}', [PharmacyController::class, 'showPatientTreatment']);
                    Route::post('/patient/fulfill', [PharmacyController::class, 'fulfillTreatment']);
                    Route::get('/list/{id}', [PharmacyController::class, 'show']);
                    Route::put('/update/{id}', [PharmacyController::class, 'update']);
                    Route::delete('/delete/{id}', [PharmacyController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [PharmacyController::class, 'toggleStatus']);
                    Route::get('/stats', [PharmacyController::class, 'pharmacyDashboardStats']);
                    Route::post('/supplies', [PharmacySupplyController::class, 'store']);
                    Route::post('/all/supplies', [PharmacySupplyController::class, 'index']);
                    Route::get('/supplies/{id}', [PharmacySupplyController::class, 'show']);

                    Route::post('/request', [PharmacyRequestController::class, 'store']);
                    Route::post('/all/request', [PharmacyRequestController::class, 'index']);
                    Route::get('/request/{id}', [PharmacyRequestController::class, 'show']);
                    Route::get('users/pharmacists', [UserController::class, 'getPharmacists']);
                });


                Route::group(['prefix' => 'medicine', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/create', [MedicationController::class, 'store']);
                    Route::post('/lists', [MedicationController::class, 'index']);
                    Route::get('/list/{id}', [MedicationController::class, 'show']);
                    Route::put('/update/{id}', [MedicationController::class, 'update']);
                    Route::delete('/delete/{id}', [MedicationController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [MedicationController::class, 'changeStatus']);
                    Route::post('/upload-csv', [MedicationController::class, 'uploadCsv']);
                    Route::get('/stats', [MedicationController::class, 'medicineDashboardStats']);
                    Route::get('/vendors', [MedicationController::class, 'listVendors']);

                    Route::post('/type', [MedicineTypeController::class, 'store']);
                    Route::post('/all/type', [MedicineTypeController::class, 'index']);
                    Route::get('/type/{id}', [MedicineTypeController::class, 'show']);
                    Route::put('/type/{id}', [MedicineTypeController::class, 'update']);
                    Route::delete('/type/{id}', [MedicineTypeController::class, 'destroy']);
                    Route::get('/fetch_medical_log', [AuditLogController::class, 'fetch_medical_log']);
                });

                Route::group(['prefix' => 'medicine-inventory', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/lists', [MedicationInventoryController::class, 'index']);
                    Route::get('/{id}', [MedicationInventoryController::class, 'show']);
                    Route::post('/', [MedicationInventoryController::class, 'store']);
                    Route::patch('/{id}/status', [MedicationInventoryController::class, 'updateStatus']);
                    Route::get('/dashboard/stats', [MedicationInventoryController::class, 'shipmentStat']);
                });

                Route::group(['prefix' => 'inventory', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/lists', [InventoryController::class, 'index']);
                    Route::get('/{id}', [InventoryController::class, 'show']);
                    Route::post('/', [InventoryController::class, 'store']);
                    Route::delete('/delete/{id}', [InventoryController::class, 'delete']);
                    Route::put('/update/{id}', [InventoryController::class, 'update']);
                    Route::get('/dashboard/stats', [InventoryController::class, 'getInventoryStats']);
                });


                Route::group(['prefix' => 'vendor', 'middleware' => 'role.pharmacy'], function () {
                    Route::post('/lists', [VendorController::class, 'index']);
                    Route::get('/{id}', [VendorController::class, 'show']);
                    Route::post('/', [VendorController::class, 'store']);
                    Route::delete('/delete/{id}', [VendorController::class, 'delete']);
                    Route::put('/update/{id}', [VendorController::class, 'update']);
                    Route::post('/update_status/{id}', [VendorController::class, 'update_status']);
                    Route::get('/dashboard/stats', [VendorController::class, 'getVendorStats']);
                });

                Route::group(['prefix' => 'billing', 'middleware' => 'role.billing'], function () {
                    Route::post('/lists', [BillingController::class, 'index']);
                    Route::post('/', [BillingController::class, 'store']);
                    Route::get('/{id}', [BillingController::class, 'show']);
                    Route::put('/{id}', [BillingController::class, 'update']);
                    Route::delete('/{id}', [BillingController::class, 'destroy']);
                    Route::get('/dashboard/stats', [BillingController::class, 'getBillingStatistics']);
                    Route::get('/all/services', [BillingController::class, 'getAllServiceUnitsAndTypes']);
                    Route::get('/service-unit/{id}', [BillingController::class, 'getBillingByServiceUnit']);
                    Route::get('/service-type/all', [BillingController::class, 'getBillingByServiceType']);
                    Route::post('/createservice', [BillingController::class, 'createservice']);
                    Route::post('/editservice', [BillingController::class, 'editservice']);
                });

                Route::group(['prefix' => 'report', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/dashboard/stats', [ReportController::class, 'getReportStatistics']);
                    Route::post('/', [ReportController::class, 'index']);
                    // patient
                    Route::get('/patient', [ReportController::class, 'getPatientReport']);
                    Route::get('/financial', [ReportController::class, 'getFinancialReport']);
                    Route::get('/system', [ReportController::class, 'getAllSystemReport']);
                    Route::get('user-activity', [ReportController::class, 'user_activity']);
                });

                //Laboratory Routes
                Route::prefix('laboratory')->group(function () {
                    Route::get('/stats', [LabController::class, 'stats']);
                    Route::post('/records', [LabController::class, 'allLabRecords']);
                    Route::get('/single-lab-record/{id}', [LabController::class, 'show']);
                });

                Route::group(['prefix' => 'auditLog', 'middleware' => 'admin.superadmin'], function () {
                    Route::post('/logs', [AuditLogController::class, 'userActivity']);
                    Route::get('data_changes', [AuditLogController::class, 'data_changes']);
                    Route::get('/logs-download/{type}', [AuditLogController::class, 'downloadAuditLog']);
                });

                Route::group(['prefix' => 'role', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/all', [RoleController::class, 'index']);
                    Route::post('/create', [RoleController::class, 'store']);
                    Route::get('/view/{id}', [RoleController::class, 'show']);
                    Route::put('/update/{id}', [RoleController::class, 'update']);
                    Route::delete('/delete/{id}', [RoleController::class, 'destroy']);
                });

                Route::group(['prefix' => 'users', 'middleware' => 'admin.superadmin'], function () {
                    Route::get('/all', [UserController::class, 'allUsers']);
                    Route::post('/create', [UserController::class, 'addUser']);
                    Route::get('/view/{id}', [UserController::class, 'viewUser']);
                    Route::put('/update/{id}', [UserController::class, 'updateUser']);
                    Route::delete('/delete/{id}', [UserController::class, 'deleteUser']);
                });

                Route::group(['prefix' => 'setting'], function () {
                    Route::put("user_update", [UserController::class, "user_update"]);
                    Route::put("account_deactive", [UserController::class, "account_deactive"]);
                    Route::put("account_deletion", [UserController::class, "account_deletion"]);
                    Route::put("user_upload_image", [UserController::class, "user_upload_image"]);
                });

                Route::group(['prefix' => 'summary'], function () {
                    Route::get("billingsummary", [BillingController::class, "billingsummary"]);
                    Route::get("regstration_list", [BillingController::class, "regstration_list"]);
                    Route::get("pharmacy_list", [BillingController::class, "pharmacy_list"]);
                    Route::get("consultation_list", [BillingController::class, "consultation_list"]);
                    Route::get("laboratory_list", [BillingController::class, "laboratory_list"]);
                    Route::get("radiology_list", [BillingController::class, "radiology_list"]);
                    Route::get("payment_daft", [BillingController::class, "payment_daft"]);
                });

                Route::group(['prefix' => 'billingmgt'], function () {
                    Route::get("/", [BillingController::class, "billingmgt"]);
                    Route::get("billingmgt_pharmacy", [BillingController::class, "billingmgt_pharmacy"]);
                    Route::get("regstration_billingmgt", [BillingController::class, "regstration_billingmgt"]);
                    Route::get("laboratory_billingmgt", [BillingController::class, "laboratory_billingmgt"]);
                    // radiology_billingmgt
                    Route::get("radiology_billingmgt", [BillingController::class, "radiology_billingmgt"]);
                    Route::get("consultation_billingmgt", [BillingController::class, "consultation_billingmgt"]);
                });
            });
        });
    });
});
