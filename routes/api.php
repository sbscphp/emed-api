<?php

use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\v1\Admin\AntenatalController;
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
use App\Http\Controllers\v1\Patient\PatientAuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\v1\Admin\MainDashBoardStatsController;
// use App\Http\Controllers\v1\Admin\ArtisanController;
use App\Http\Controllers\v1\Admin\ArtisanController;
use App\Http\Controllers\v1\Admin\BulkUploadController;
use App\Http\Controllers\v1\Notification\NotificationController;
use App\Http\Controllers\v1\Admin\Consultation_Service_Bill;
use App\Http\Controllers\v1\Admin\HivAidsController;
use App\Http\Controllers\v1\Admin\ImmunizationController;
use App\Http\Controllers\v1\Admin\Lab_Service_Controller;
use App\Http\Controllers\v1\Admin\PharmacyServiceController;
use App\Http\Controllers\v1\Admin\Radiology_service_Controller;
use App\Http\Controllers\v1\Admin\Revamp\AdmissionController;
use App\Http\Controllers\v1\Admin\Revamp\AppointmentController;
use App\Http\Controllers\v1\Admin\Revamp\AuthenticationController;
use App\Http\Controllers\v1\Admin\Revamp\BillingController as RevampBillingController;
use App\Http\Controllers\v1\Admin\Revamp\BillingServiceController;
use App\Http\Controllers\v1\Admin\Revamp\PayoutAccountController;
use App\Http\Controllers\v1\Admin\Revamp\ConsultationController as RevampConsultationController;
use App\Http\Controllers\v1\Admin\Revamp\DashboardController;
use App\Http\Controllers\v1\Admin\Revamp\DepartmentController;
use App\Http\Controllers\v1\Admin\Revamp\LabController as RevampLabController;
use App\Http\Controllers\v1\Admin\Revamp\LabParameterController;
use App\Http\Controllers\v1\Admin\Revamp\PharmacyController as RevampPharmacyController;
use App\Http\Controllers\v1\Admin\Revamp\RadiologyController as RevampRadiologyController;
use App\Http\Controllers\v1\Admin\Revamp\ReportController as RevampReportController;
use App\Http\Controllers\v1\Admin\Revamp\ServiceCategoryController;
use App\Http\Controllers\v1\Admin\Revamp\ServiceController;
use App\Http\Controllers\v1\Admin\Revamp\WardBedController;
use App\Http\Controllers\v1\GeneralController;
use App\Services\HivAids\HivAidsService;
// use App\Models\Immunization;
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

    /** UPLOAD FILES & DOCUMENTS */
    Route::group(['prefix' => 'upload'], function () {
        Route::post('/single/string/file', [GeneralController::class, 'uploadSingleFileString']);
        Route::post('/single/binary/file', [GeneralController::class, 'uploadSingleFileBinary']);
        Route::post('/multiple/binary/file', [GeneralController::class, 'uploadMultipleFileBinary']);
        Route::post('/multiple/string/file', [GeneralController::class, 'uploadMultipleFileString']);
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

    // Route::group(['prefix' => 'admin'], function () {
    //     Route::post('/login', [RegistrationController::class, 'adminLogin']);
    // });

    Route::group(['prefix' => 'admin'], function () {
        Route::post('/register', [AuthenticationController::class, 'register']);
        Route::post('/resend/otp', [AuthenticationController::class, 'resendOtp']);
        Route::post('/verify/otp', [AuthenticationController::class, 'verifyOtp']);
        Route::get('/resend/email', [AuthenticationController::class, 'resendEmailVerification']);
        Route::get('/verify/email/{toke}/{email}', [AuthenticationController::class, 'verifyEmail']);
        Route::post('/find/hospitals', [AuthenticationController::class, 'findHospitals']);
        Route::post('/login', [AuthenticationController::class, 'login']);
        Route::post('/contact', [GeneralController::class, 'contact']);
        // Route::post('/register', [RegistrationController::class, 'onboardTenant']);
    });
    Route::post('/logout', [RegistrationController::class, 'logout']);

    /**
     * PATIENT MOBILE APP
     *
     * Moved out to routes/mobile.php, where the app's endpoints are grouped one
     * module at a time (auth, account, appointment, ...) instead of being spread
     * through this file. That group is registered in bootstrap/app.php and is
     * served under /api/v1/mobile.
     */

    Route::group(["middleware" => ["auth:api"]], function () {
        Route::group(['middleware' => ["tenant"]], function () {
            Route::group(['prefix' => 'general'], function () {
                Route::get('/all/laboratory/test', [GeneralController::class, 'allLabTest']);
                Route::get('/all/radiology/test', [GeneralController::class, 'allRadiologyTest']);
                Route::get('/all/medicine', [GeneralController::class, 'allMedicine']);
                Route::get('/medications', [GeneralController::class, 'medications']);
                Route::get('/all/services', [GeneralController::class, 'allService']);
                Route::get('/all/services/unit', [GeneralController::class, 'allServiceUnits']);
                Route::get('/all/inventory/drug', [GeneralController::class, 'allInventoryDrugs']);
                Route::get('/all/medication', [GeneralController::class, 'allMedication']);
                Route::get('/all/pharmacy', [GeneralController::class, 'allPharmacy']);
                Route::get('/all/lab_category', [GeneralController::class, 'allLabCategory']);
                Route::get('/lab_test/by_category/{id}', [GeneralController::class, 'labTestByCategory']);
                Route::get('/view/consultation/{id}', [GeneralController::class, 'viewConsultation']);
                Route::get('/show/service/{id}', [TriageController::class, 'showService']);
            });
            // Route::get('/test_all-records', [RecordManagementController::class, 'allRecords']);


            Route::get('/me', [RegistrationController::class, 'me']);
            Route::get('/check_is_change_password', [RegistrationController::class, 'check_is_change_password']);
            Route::get('/user_information', [RegistrationController::class, 'user_information']);
            Route::put('/change_password', [RegistrationController::class, 'change_password']);
            Route::get('/refreshToken', [RegistrationController::class, 'refreshToken']);

            Route::group(['prefix' => 'admin', "namespace" => "v1\Admin"], function () {
                // Dashboard stats
                Route::group(['prefix' => 'dashboard'], function () {
                    Route::get('/', [DashboardController::class, "index"]);
                });

                Route::group(['prefix' => 'main-stats'], function () {
                    Route::get("/main-page", [MainDashBoardStatsController::class, "index"]);
                    Route::get("/top_drugs", [MainDashBoardStatsController::class, "top_drugs"]);
                    Route::get("/patient_diagnosis", [MainDashBoardStatsController::class, "patient_diagnosis"]);
                    Route::get("/recent_patient",   [MainDashBoardStatsController::class, "recent_patient"]);
                    Route::get("/yearly_patient",   [MainDashBoardStatsController::class, "yearly_patient"]);
                    Route::get("/patient_age_gender", [MainDashBoardStatsController::class, "patient_age_gender"]);
                    Route::get('appointment', [MainDashBoardStatsController::class, "appointment"]);
                    Route::get('lab_test_year', [MainDashBoardStatsController::class, "lab_test_year"]);
                    Route::get('/revenue', [MainDashBoardStatsController::class, "revenue"]);
                    Route::get('/in_and_out/patient', [MainDashBoardStatsController::class, "in_and_out_patient"]);
                    Route::get('/appointments', [MainDashBoardStatsController::class, "appointments"]);
                    Route::get('/departments/all', [MainDashBoardStatsController::class, "departments"]);
                });

                //Record routes
                Route::group(['prefix' => 'record'], function () {
                    Route::group(['prefix' => 'patient'], function () {
                        Route::get('/', [RecordManagementController::class, 'index']);
                        Route::post('/create', [RecordManagementController::class, 'store']);
                        Route::put('/update/{id}', [RecordManagementController::class, 'update']);
                        Route::get('/fetch/patient-documents', [RecordManagementController::class, 'fetchPatientDocuments']);
                        Route::get('/show/patient-document/{id}', [RecordManagementController::class, 'showPatientDocument']);
                        Route::put('/upload/patient-documents/{id}', [RecordManagementController::class, 'uploadPatientDocuments']);
                        Route::delete('/delete/patient-document/{id}', [RecordManagementController::class, 'deletePatientDocument']);
                        Route::delete('/delete/{id}', [RecordManagementController::class, 'delete']);
                        // Bulk Upload (must be declared before the /{id} wildcard)
                        // Re-send the patient app invitation (declared before the
                        // /{id} wildcard so it is not swallowed by it).
                        Route::post('/resend-invitation/{id}', [RecordManagementController::class, 'resendInvitation']);
                        Route::post('/bulk-upload', [RecordManagementController::class, 'bulkUpload']);
                        Route::get('/bulk-upload/template', [BulkUploadController::class, 'template']);
                        Route::get('/bulk-upload/{batch_id}/errors', [BulkUploadController::class, 'errors'])->name('patient-bulk-upload.errors');
                        Route::get('/bulk-upload/{batch_id}', [BulkUploadController::class, 'show']);
                        // Wildcard — must stay last to avoid swallowing the above routes
                        Route::get('/{id}', [RecordManagementController::class, 'show']);

                        Route::get('/all/patients/care-notes', [RecordManagementController::class, 'patientCareNotes']);
                        Route::post('/add/patients/care-notes', [RecordManagementController::class, 'addPatientCareNotes']);
                        Route::get('/view/patients/care-notes/{id}', [RecordManagementController::class, 'viewPatientCareNotes']);
                        Route::put('/update/patients/care-notes/{id}', [RecordManagementController::class, 'updatePatientCareNotes']);
                    });

                    Route::group(['prefix' => 'visit'], function () {
                        Route::get('/', [RecordManagementController::class, 'patientVisitRecords']);
                        Route::post('/initiate', [RecordManagementController::class, 'initiateVisit']);
                        Route::get('/{id}', [RecordManagementController::class, 'showVisit']);
                    });
                });

                // Nurse services routes
                Route::group(['prefix' => 'services'], function () {
                    Route::get('/', [TriageController::class, 'index']);
                    Route::post('/triage/initiate', [TriageController::class, 'store']);
                    Route::get('/show/{id}', [TriageController::class, 'showService']);
                    Route::get('/investigation/order', [TriageController::class, 'investigationOrders']);
                    Route::post('/export/{format}', [TriageController::class, 'exportTriagePatients']);
                    Route::get('/view/radiology/investigation/order/{id}/{visit}', [TriageController::class, "viewRadiologyInvestigationOrders"]);
                    Route::get('/view/laboratory/investigation/order/{id}/{visit}', [TriageController::class, "viewLaboratoryInvestigationOrders"]);
                    Route::get('/view/pharmacy/investigation/order/{id}/{visit}', [TriageController::class, "viewPharmacyInvestigationOrders"]);

                    Route::group(['prefix' => 'antenatal'], function () {
                        Route::post("/details/create",  [AntenatalController::class, "createAntenatalRecord"]);
                        Route::post("/lab/test/create", [AntenatalController::class, "createAntenatalLabTest"]);
                        Route::post("/delivery/create", [AntenatalController::class, "createDeliveryDetails"]);
                        Route::post("/new/born/create", [AntenatalController::class, "createNewBorn"]);
                        Route::get("/summary/{id}", [AntenatalController::class, "summary"]);
                    });
                });

                Route::group(['prefix' => 'hiv_aids'], function () {
                    Route::post("/counselling/details/create",  [HivAidsController::class, "createCouncellingDetails"]);
                    Route::post("/observation/create", [HivAidsController::class, "createObservation"]);
                    Route::get("/summary/{id}", [HivAidsController::class, "summary"]);
                });

                Route::group(['prefix' => 'immunization'], function () {
                    Route::post("/create_immunization",  [ImmunizationController::class, "create_immunization"]);
                    Route::post("/dosage_admin", [ImmunizationController::class, "dosage_admin"]);
                    Route::get("/summary/{id}", [ImmunizationController::class, "summary"]);
                    Route::post("/observetation_recommandation", [ImmunizationController::class, "observetation_recommandation"]);
                });

                //Consultant routes
                Route::group(['prefix' => 'consultation'], function () {
                    Route::get('/', [RevampConsultationController::class, 'index']);
                    Route::post('/patient/create', [RevampConsultationController::class, 'createConsultation']);
                    Route::put('/patient/update/{id}', [RevampConsultationController::class, 'updatePatientConsultation']);
                    Route::put('/end/{id}', [RevampConsultationController::class, 'endConsultation']);
                    Route::post('/patient/lab/test', [RevampConsultationController::class, 'createLabTest']);
                    Route::post('/patient/radiology/test', [RevampConsultationController::class, 'createRadiologyTest']);
                    Route::post('/patient/treatment', [RevampConsultationController::class, 'createTreatment']);
                    Route::post('/patient/surgery', [RevampConsultationController::class, 'createSurgery']);
                    Route::get('/view/{id}', [RevampConsultationController::class, 'viewConsultation']);
                    Route::get('/recent/{id}', [RevampConsultationController::class, 'recentConsultation']);
                });

                // Old Consultant routes
                Route::group(['prefix' => 'consultant'], function () {
                    Route::get("/consultaton_stats", [ConsultationController::class, "consultaton_stats"]);
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

                // Pharmacy routes
                Route::group(['prefix' => 'pharmacy'], function () {
                    Route::get('/patient/logs', [RevampPharmacyController::class, 'index']);
                    Route::get('/patient/treatments', [RevampPharmacyController::class, 'patientTreatments']);
                    Route::get('/patient/treatment/view/{id}', [RevampPharmacyController::class, 'viewPatientTreatment']);
                    Route::post('/patient/treatment/fulfill', [RevampPharmacyController::class, 'fulfillTreatment']);
                    Route::get('/lists', [RevampPharmacyController::class, 'allPharmacies']);
                    Route::post('/create', [RevampPharmacyController::class, 'createPharmacy']);
                    Route::get('/view/{id}', [RevampPharmacyController::class, 'viewPharmacy']);
                    Route::put('/update/{id}', [RevampPharmacyController::class, 'updatePharmacy']);
                    Route::put('/toggle-status/{id}', [RevampPharmacyController::class, 'togglePharmacyStatus']);
                    Route::delete('/delete/{id}', [RevampPharmacyController::class, 'destroyPharmacy']);

                    Route::post('/supplies', [PharmacySupplyController::class, 'store']);
                    Route::post('/all/supplies', [PharmacySupplyController::class, 'index']);
                    Route::get('/supplies/{id}', [PharmacySupplyController::class, 'show']);

                    Route::post('/request', [PharmacyRequestController::class, 'store']);
                    Route::get('/all/request', [PharmacyRequestController::class, 'index']);
                    Route::get('/request/{id}', [PharmacyRequestController::class, 'show']);
                    Route::put('/request/update/{id}', [PharmacyRequestController::class, 'updateRequest']);
                    Route::put('/request/supply/{id}', [PharmacyRequestController::class, 'supplyRequest']);
                    Route::delete('/request/delete/{id}', [PharmacyRequestController::class, 'deleteRequest']);
                    Route::get('users/pharmacists', [UserController::class, 'getPharmacists']);
                });

                // Old Pharmacy routes
                // Route::group(['prefix' => 'pharmacy', 'middleware' => 'role.pharmacy'], function () {
                //     Route::post('/lists', [PharmacyController::class, 'index']);
                //     Route::get('/stats', [PharmacyController::class, 'pharmacyDashboardStats']);
                //     Route::post('/create', [PharmacyController::class, 'store']);
                //     Route::post('/patient/lists', [PharmacyController::class, 'treatmentLogs']);
                //     Route::get('/patient/{patientId}', [PharmacyController::class, 'showPatientTreatment']);
                //     Route::post('/patient/fulfill', [PharmacyController::class, 'fulfillTreatment']);
                //     Route::get('/list/{id}', [PharmacyController::class, 'show']);
                //     // Route::put('/update/{id}', [PharmacyController::class, 'update']);
                //     // Route::delete('/delete/{id}', [PharmacyController::class, 'destroy']);
                //     Route::patch('/{id}/toggle-status', [PharmacyController::class, 'toggleStatus']);
                //     Route::post('/supplies', [PharmacySupplyController::class, 'store']);
                //     Route::post('/all/supplies', [PharmacySupplyController::class, 'index']);
                //     Route::get('/supplies/{id}', [PharmacySupplyController::class, 'show']);

                //     Route::post('/request', [PharmacyRequestController::class, 'store']);
                //     Route::post('/all/request', [PharmacyRequestController::class, 'index']);
                //     Route::get('/request/{id}', [PharmacyRequestController::class, 'show']);
                //     Route::get('users/pharmacists', [UserController::class, 'getPharmacists']);
                // });

                Route::group(['prefix' => 'medicine'], function () {
                    Route::post('/create', [MedicationController::class, 'store']);
                    Route::get('/lists', [MedicationController::class, 'index']);
                    Route::get('/list/{id}', [MedicationController::class, 'show']);
                    Route::put('/update/{id}', [MedicationController::class, 'update']);
                    Route::delete('/delete/{id}', [MedicationController::class, 'destroy']);
                    Route::patch('/{id}/toggle-status', [MedicationController::class, 'changeStatus']);
                    Route::post('/upload-csv', [MedicationController::class, 'uploadCsv']);
                    Route::post('/bulk-upload', [MedicationController::class, 'bulkUpload']);
                    // Generic Bulk Upload Endpoints
                    Route::get('/bulk-upload/template', [BulkUploadController::class, 'template']);
                    Route::get('/bulk-upload/{batch_id}', [BulkUploadController::class, 'show']);
                    Route::get('/bulk-upload/{batch_id}/errors', [BulkUploadController::class, 'errors'])->name('bulk-upload.errors');

                    Route::get('/stats', [MedicationController::class, 'medicineDashboardStats']);
                    Route::get('/vendors', [MedicationController::class, 'listVendors']);

                    Route::post('/type', [MedicineTypeController::class, 'store']);
                    Route::post('/all/type', [MedicineTypeController::class, 'index']);
                    Route::get('/type/{id}', [MedicineTypeController::class, 'show']);
                    Route::put('/type/{id}', [MedicineTypeController::class, 'update']);
                    Route::delete('/type/{id}', [MedicineTypeController::class, 'destroy']);
                    Route::get('/fetch_medical_log', [AuditLogController::class, 'fetch_medical_log']);
                });

                Route::group(['prefix' => 'medicine-inventory'], function () {
                    Route::post('/lists', [MedicationInventoryController::class, 'index']);
                    Route::get('/{id}', [MedicationInventoryController::class, 'show']);
                    Route::post('/', [MedicationInventoryController::class, 'store']);
                    Route::put('/update/shipment/{id}', [MedicationInventoryController::class, 'updateShipment']);
                    Route::patch('/{id}/status', [MedicationInventoryController::class, 'updateStatus']);
                    Route::get('/dashboard/stats', [MedicationInventoryController::class, 'shipmentStat']);
                });

                Route::group(['prefix' => 'inventory'], function () {
                    Route::get('/lists', [InventoryController::class, 'index']);
                    Route::get('/{id}', [InventoryController::class, 'show']);
                    Route::post('/', [InventoryController::class, 'store']);
                    Route::delete('/delete/{id}', [InventoryController::class, 'destroy']);
                    Route::put('/update/{id}', [InventoryController::class, 'update']);
                    Route::get('/dashboard/stats', [InventoryController::class, 'getInventoryStats']);
                });


                Route::group(['prefix' => 'vendor'], function () {
                    Route::get('/fetch/all', [VendorController::class, 'all']);
                    Route::get('/lists', [VendorController::class, 'index']);
                    Route::get('/{id}', [VendorController::class, 'show']);
                    Route::post('/', [VendorController::class, 'store']);
                    Route::delete('/delete/{id}', [VendorController::class, 'delete']);
                    Route::put('/update/{id}', [VendorController::class, 'update']);
                    Route::post('/update_status/{id}', [VendorController::class, 'update_status']);
                    Route::get('/dashboard/stats', [VendorController::class, 'getVendorStats']);
                });

                // Billing routes
                Route::group(['prefix' => 'billing'], function () {

                    // Where this hospital is settled when a patient pays from
                    // the mobile app. Patients pay the platform's Paystack
                    // account and Paystack splits each charge to the hospital's
                    // subaccount, which is what these details register. No API
                    // key is involved — the hospital supplies a bank account,
                    // the platform holds the keys.
                    //
                    // Declared before /{id} so the static segments are matched
                    // as themselves rather than read as an id.
                    Route::group(['prefix' => 'payout-account'], function () {
                        Route::get('/banks', [PayoutAccountController::class, 'banks']);
                        Route::post('/resolve', [PayoutAccountController::class, 'resolve']);
                        Route::post('/retry', [PayoutAccountController::class, 'retry']);

                        Route::get('/', [PayoutAccountController::class, 'show']);
                        Route::put('/', [PayoutAccountController::class, 'update']);
                    });

                    Route::get('/', [RevampBillingController::class, 'index']);
                    Route::get('/view/{id}', [RevampBillingController::class, 'viewBilling']);
                    Route::post('/make/payment', [RevampBillingController::class, 'makePayment']);
                    Route::get('/invoice', [RevampBillingController::class, 'invoice']);
                    Route::get('/invoice/view/{id}', [RevampBillingController::class, 'viewInvoice']);
                    Route::get('/summary', [RevampBillingController::class, 'summary']);
                    Route::get('/service', [RevampBillingController::class, 'service']);
                    Route::post('/save_as_daft', [BillingController::class, 'save_as_daft']);
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

                // Old Billing routes
                // Route::group(['prefix' => 'billing', 'middleware' => 'role.billing'], function () {
                //     Route::post('/lists', [BillingController::class, 'index']);
                // Route::post('/', [BillingController::class, 'store']);

                //     // save_as_daft
                //     Route::post('/save_as_daft', [BillingController::class, 'save_as_daft']);
                //     Route::get('/{id}', [BillingController::class, 'show']);
                //     Route::put('/{id}', [BillingController::class, 'update']);
                //     Route::delete('/{id}', [BillingController::class, 'destroy']);
                //     Route::get('/dashboard/stats', [BillingController::class, 'getBillingStatistics']);
                //     Route::get('/all/services', [BillingController::class, 'getAllServiceUnitsAndTypes']);
                //     Route::get('/service-unit/{id}', [BillingController::class, 'getBillingByServiceUnit']);
                //     Route::get('/service-type/all', [BillingController::class, 'getBillingByServiceType']);
                //     Route::post('/createservice', [BillingController::class, 'createservice']);
                //     Route::post('/editservice', [BillingController::class, 'editservice']);
                // });

                // Report routes
                Route::group(['prefix' => 'reports'], function () {
                    Route::get('/', [RevampReportController::class, 'index']);
                    Route::get('/all', [RevampReportController::class, 'fetchAllReports']);
                });

                //  Old Report Routes
                Route::group(['prefix' => 'report'], function () {
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
                    Route::get('/', [RevampLabController::class, 'index']);
                    Route::get('/all', [RevampLabController::class, 'allTests']);
                    Route::get('/result-form/{id}', [RevampLabController::class, 'resultForm']);
                    Route::get('/{id}', [RevampLabController::class, 'show']);
                    Route::put('/update/result/{id}', [RevampLabController::class, 'updateResult']);
                    Route::put('/update/test/status/{id}', [RevampLabController::class, 'updateTestStatus']);
                    // POST as well as PUT: PHP only parses multipart/form-data
                    // bodies on POST, so a real file upload cannot arrive on PUT.
                    Route::match(['post', 'put'], '/upload-result', [RevampLabController::class, 'uploadLabResult']);
                    Route::get('/summary/{id}', [RevampLabController::class, 'patientVisitSummary']);
                    Route::get('/patient/{id}', [RevampLabController::class, 'patientDetails']);
                });

                //Old Laboratory Routes
                Route::prefix('laboratory')->group(function () {
                    Route::get('/stats', [LabController::class, 'stats']);
                    Route::post('/records', [LabController::class, 'allLabRecords']);
                    Route::get('/single-lab-record', [LabController::class, 'show']);
                    Route::put('/test/update/{id}', [LabController::class, 'updateTest']);
                    Route::get('/patient/{id}', [LabController::class, 'patientDetails']);
                    Route::get('/patient/visit/summary/{id}', [LabController::class, 'patientVisitSummary']);
                    Route::post('/result', [LabController::class, 'updateResult']);
                });

                // Radiology routes
                Route::group(['prefix' => 'radiology'], function () {
                    Route::get('/', [RevampRadiologyController::class, 'index']);
                    Route::get('/all', [RevampRadiologyController::class, 'allTests']);
                    Route::get('/{id}', [RevampRadiologyController::class, 'show']);
                    Route::put('/update/result/{id}', [RevampRadiologyController::class, 'updateResult']);
                    Route::put('/update/test/status/{id}', [RevampRadiologyController::class, 'updateTestStatus']);
                    // POST as well as PUT: PHP only parses multipart/form-data
                    // bodies on POST, so a real file upload cannot arrive on PUT.
                    Route::match(['post', 'put'], '/upload-result', [RevampRadiologyController::class, 'uploadRadiologyResult']);
                    Route::get('/summary/{id}', [RevampRadiologyController::class, 'patientVisitSummary']);
                    Route::get('/patient/{id}', [RevampRadiologyController::class, 'patientDetails']);
                });

                Route::group(['prefix' => 'auditLog'], function () {
                    Route::post('/logs', [AuditLogController::class, 'userActivity']);
                    Route::get('/user/activity', [AuditLogController::class, 'userActivityRecords']);
                    Route::get('data_changes', [AuditLogController::class, 'data_changes']);
                    Route::get('/logs-download', [AuditLogController::class, 'downloadAuditLog']);
                });

                Route::group(['prefix' => 'role'], function () {
                    Route::get('/all', [RoleController::class, 'index']);
                    Route::get('/permissions', [RoleController::class, 'permissions']);
                    Route::post('/create', [RoleController::class, 'store']);
                    Route::get('/view/{id}', [RoleController::class, 'show']);
                    Route::put('/update/{id}', [RoleController::class, 'update']);
                    Route::delete('/delete/{id}', [RoleController::class, 'destroy']);
                });

                Route::group(['prefix' => 'users'], function () {
                    Route::get('/all', [UserController::class, 'allUsers']);
                    Route::get('/roles', [UserController::class, 'allRoles']);
                    Route::post('/create', [UserController::class, 'addUser']);
                    Route::get('/view/{id}', [UserController::class, 'viewUser']);
                    Route::put('/update/{id}', [UserController::class, 'updateUser']);
                    Route::put('/toggle/status/{id}', [UserController::class, 'toggleStatus']);
                    Route::delete('/delete/{id}', [UserController::class, 'deleteUser']);
                });

                Route::group(['prefix' => 'setting'], function () {
                    Route::put("user_update/{id}", [UserController::class, "user_update"]);
                    Route::put("account_deactive/{id}", [UserController::class, "account_deactive"]);
                    Route::delete("account_deletion/{id}", [UserController::class, "account_deletion"]);
                    Route::put("hospital_information/update", [UserController::class, "hospital_information"]);
                    Route::put("user_upload_image", [UserController::class, "user_upload_image"]);
                });

                Route::group(['prefix' => 'summary'], function () {
                    Route::get("billingsummary", [BillingController::class, "billingsummary"]);
                    Route::get("registration_list", [BillingController::class, "registration_list"]);
                    Route::get("pharmacy_list", [BillingController::class, "pharmacy_list"]);
                    Route::get("consultation_list", [BillingController::class, "consultation_list"]);
                    Route::get("laboratory_list", [BillingController::class, "laboratory_list"]);
                    Route::get("radiology_list", [BillingController::class, "radiology_list"]);
                    // Route::get("payment_daft", [BillingController::class, "payment_daft"]);
                    Route::get("/payment_daft", [BillingController::class, 'payment_billing_daft']);
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

                // Old Radiology
                // Route::group(['prefix' => 'radiology'], function () {
                //     Route::get('/', [RadiologyController::class, "index"]);
                //     Route::get('/patient', [RadiologyController::class, "patient"]);
                //     Route::post('/result', [RadiologyController::class, "result"]);
                //     Route::put('/update/result/{id}', [RadiologyController::class, "updateResult"]);
                //     Route::put('/update/result/status/{id}', [RadiologyController::class, "updateResultStaus"]);
                //     Route::post('/radiology_examination', [RadiologyController::class, 'radiology_examination']);
                //     Route::get('/radiology_examination', [RadiologyController::class, 'radiology_examination_get']);
                // });

                Route::group(['prefix' => 'patient_consultation_summary'], function () {
                    Route::get('/', [MainDashBoardStatsController::class, "patient_consultation_summary_data"]);
                    Route::post("/consultation_details", [ImmunizationController::class, "consultation_details"]);
                    Route::get("/consultation_details", [ImmunizationController::class, "consultation_details_get"]);
                    Route::post("/consultation_details_laboratory", [ImmunizationController::class, "consultation_details_laborartory"]);
                    Route::get("consultation_details_laboratory", [ImmunizationController::class, "consultation_details_laborartory_get"]);
                    Route::post("/consultation_detail_radiology", [ImmunizationController::class, "consultation_detail_radiology"]);
                    Route::get("/consultation_detail_radiology", [ImmunizationController::class, "consultation_detail_radiology_get"]);
                    Route::post("/consultation_detail_treatment", [ImmunizationController::class, "consultation_detail_treatment"]);
                    Route::get("/consultation_detail_treatment", [ImmunizationController::class, "consultation_detail_treatment_get"]);
                });

                Route::group(['prefix' => 'service'], function () {
                    Route::post("/create_service",  [ImmunizationController::class, "create_service"]);
                    Route::put("/edit_service",  [ImmunizationController::class, "edit_service"]);
                    Route::get("/all_service", [ImmunizationController::class, "service"]);

                    Route::post('/createpharmacyservice', [PharmacyServiceController::class, 'createpharmacyservice']);
                    Route::put('/editpharmacyservice', [PharmacyServiceController::class, 'editpharmacyservice']);
                    Route::get('/pharmacyService_all', [PharmacyServiceController::class, 'pharmacyService_all']);

                    Route::post('/create_consultation_service', [Consultation_Service_Bill::class, "create_consultation_service"]);
                    Route::put('/edit_consultation_service', [Consultation_Service_Bill::class, "edit_consultation_service"]);
                    Route::get('/all_consultation_service', [Consultation_Service_Bill::class, "all_consultation_service"]);

                    Route::post('/create_lab_service', [Lab_Service_Controller::class, "create_lab_service"]);
                    Route::put('/edit_lab_service', [Lab_Service_Controller::class, "edit_lab_service"]);
                    Route::post('/lab_services/{labTestId}/parameters', [LabParameterController::class, 'assignToLabTest']);
                    Route::get('/lab_services/{labTestId}/parameters/show', [LabParameterController::class, 'showLabTestParameters']);
                    Route::put('/lab_services/{labTestId}/parameters/{parameterId}', [LabParameterController::class, 'updateLabTestParameter']);
                    Route::delete('/lab_services/{labTestId}/parameters/{parameterId}', [LabParameterController::class, 'destroyLabTestParameter']);
                    Route::delete('/delete_lab_service/{id}', [Lab_Service_Controller::class, "delete_lab_service"]);
                    Route::get('/lab_Service_all', [Lab_Service_Controller::class, "labService_all"]);


                    Route::get('/all_radiology_category', [Radiology_service_Controller::class, "all_radiology_category"]);
                    Route::post('/create_radiology_service', [Radiology_service_Controller::class, "create_radiology_service"]);
                    Route::put('/edit_radiology_service', [Radiology_service_Controller::class, "edit_radiology_service"]);
                    Route::get('/all_radiology_service', [Radiology_service_Controller::class, "all_radiology_service"]);
                });

                Route::group(['prefix' => 'service_categories'], function () {
                    Route::get('/', [ServiceCategoryController::class, "index"]);
                    Route::post('/create', [ServiceCategoryController::class, "store"]);
                    Route::get('/{id}', [ServiceCategoryController::class, "show"]);
                    Route::put('/update/{id}', [ServiceCategoryController::class, "update"]);
                    Route::delete('/delete/{id}', [ServiceCategoryController::class, "destroy"]);
                });

                Route::group(['prefix' => 'lab_parameters'], function () {
                    Route::get('/', [LabParameterController::class, 'index']);
                    Route::post('/create', [LabParameterController::class, 'store']);
                    Route::get('/{id}', [LabParameterController::class, 'show']);
                    Route::put('/update/{id}', [LabParameterController::class, 'update']);
                    Route::delete('/delete/{id}', [LabParameterController::class, 'destroy']);
                });

                Route::group(['prefix' => 'ward_beds'], function () {
                    Route::get('/fetch/all', [WardBedController::class, 'index']);
                    Route::post('/create', [WardBedController::class, 'store']);
                    Route::get('/{id}', [WardBedController::class, 'show']);
                    Route::put('/update/{id}', [WardBedController::class, 'update']);
                    Route::delete('/delete/{id}', [WardBedController::class, 'destroy']);
                });

                Route::group(['prefix' => 'admissions'], function () {
                    Route::get('/', [AdmissionController::class, 'index']);
                    Route::get('/form/options', [AdmissionController::class, 'options']);
                    Route::get('/fetch/wards', [AdmissionController::class, 'wards']);
                    Route::get('/fetch/wards/{wardId}/bed-spaces', [AdmissionController::class, 'wardBedSpaces']);
                    Route::post('/admit/patient', [AdmissionController::class, 'admitPatient']);
                    Route::post('/schedule/patient', [AdmissionController::class, 'scheduleAdmission']);
                    Route::post('/emergency/patient', [AdmissionController::class, 'emergencyAdmission']);
                    Route::post('/transfer/patient', [AdmissionController::class, 'transferPatient']);
                    Route::post('/cancel/patient', [AdmissionController::class, 'cancelAdmission']);
                    Route::post('/discharge/patient', [AdmissionController::class, 'dischargePatient']);
                    Route::put('/update/{id}', [AdmissionController::class, 'updateAdmission']);
                    Route::get('/patients/{id}', [AdmissionController::class, 'viewPatient']);
                    Route::get('/all/patients/visits', [AdmissionController::class, 'viewPatientVisit']);
                    Route::get('/all/patients/care-notes', [AdmissionController::class, 'patientCareNotes']);
                    Route::post('/add/patients/care-notes', [AdmissionController::class, 'addPatientCareNotes']);
                    Route::get('/view/patients/care-notes/{id}', [AdmissionController::class, 'viewPatientCareNotes']);
                    Route::put('/update/patients/care-notes/{id}', [AdmissionController::class, 'updatePatientCareNotes']);
                    Route::get('/all/patients/visit/drugs/{id}', [AdmissionController::class, 'viewPatientVisitDrugs']);
                    Route::get('/all/patients/drug-charts', [AdmissionController::class, 'patientDrugCharts']);
                    Route::post('/add/patients/drug-charts', [AdmissionController::class, 'addPatientDrugCharts']);
                    Route::get('/view/patients/drug-charts/{id}', [AdmissionController::class, 'viewPatientDrugCharts']);
                    // Kept last so the static admission routes above are matched first.
                    Route::get('/{id}', [AdmissionController::class, 'show'])->whereNumber('id');
                });

                Route::group(['prefix' => 'notifications'], function () {
                    Route::get('/', [NotificationController::class, "index"]);
                    Route::put('/mark_read/{id}', [NotificationController::class, 'markAsRead']);
                    Route::post('/all/mark_read', [NotificationController::class, 'markAllAsRead']);
                });

                Route::group(['prefix' => 'departments'], function () {
                    Route::get('/', [DepartmentController::class, "index"]);
                    Route::post('/', [DepartmentController::class, 'store']);
                    // The doctors that consult in a department, which is what the
                    // patient app's booking flow offers once a department is
                    // chosen. Declared before the /{id} wildcard.
                    Route::get('/{id}/doctors', [DepartmentController::class, 'doctors'])->whereNumber('id');
                    Route::put('/{id}/doctors', [DepartmentController::class, 'syncDoctors'])->whereNumber('id');
                    Route::get('/{id}', [DepartmentController::class, 'show']);
                    Route::put('/{id}', [DepartmentController::class, 'update']);
                    Route::put('/toggle-status/{id}', [DepartmentController::class, 'toggleStatus']);
                    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
                });

                Route::group(['prefix' => 'appointments'], function () {
                    Route::get('/', [AppointmentController::class, "index"]);
                    Route::post('/', [AppointmentController::class, 'store']);
                    Route::get('/{id}', [AppointmentController::class, 'show']);
                    Route::put('/{id}', [AppointmentController::class, 'update']);
                    Route::delete('/{id}', [AppointmentController::class, 'destroy']);
                });

                // The services and pricing catalogue. Prefixed hospital_services
                // because /admin/services already belongs to the nurse triage
                // routes above.
                Route::group(['prefix' => 'hospital_services'], function () {
                    Route::get('/', [ServiceController::class, "index"]);
                    Route::post('/', [ServiceController::class, 'store']);
                    Route::get('/{id}/sub_services', [ServiceController::class, 'subServices'])->whereNumber('id');
                    Route::get('/{id}', [ServiceController::class, 'show'])->whereNumber('id');
                    Route::put('/toggle-status/{id}', [ServiceController::class, 'toggleStatus'])->whereNumber('id');
                    Route::put('/{id}', [ServiceController::class, 'update'])->whereNumber('id');
                    Route::delete('/{id}', [ServiceController::class, 'destroy'])->whereNumber('id');
                });

                // The sub-services of the catalogue above.
                Route::group(['prefix' => 'billing_services'], function () {
                    Route::get('/', [BillingServiceController::class, "index"]);
                    Route::post('/', [BillingServiceController::class, 'store']);
                    Route::get('/{id}', [BillingServiceController::class, 'show']);
                    Route::put('/{id}', [BillingServiceController::class, 'update']);
                    Route::delete('/{id}', [BillingServiceController::class, 'destroy']);
                });
            });
        });
    });
});
