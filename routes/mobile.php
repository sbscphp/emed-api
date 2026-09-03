<?php

use App\Http\Controllers\v1\Patient\AdmissionController;
use App\Http\Controllers\v1\Patient\AppointmentController;
use App\Http\Controllers\v1\Patient\DashboardController;
use App\Http\Controllers\v1\Patient\LaboratoryController;
use App\Http\Controllers\v1\Patient\PatientAuthController;
use App\Http\Controllers\v1\Patient\ProfileController;
use App\Http\Controllers\v1\Patient\RadiologyController;
use App\Http\Controllers\v1\Patient\RecordController;
use App\Http\Controllers\v1\Patient\VitalsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Patient Mobile App Routes
|--------------------------------------------------------------------------
|
| Every endpoint the patient mobile app calls lives here, one group per module,
| so a module can be read, reviewed and extended in one place instead of being
| threaded through the far longer admin route file.
|
| Registered in bootstrap/app.php under the api/v1 prefix, so the URLs are the
| ones the app already integrates against — this file changed where the routes
| are declared, never what they are called:
|
|     auth        /api/v1/patient/auth/...
|     account     /api/v1/patient/biometric, /change-password, /hospitals/mine
|     dashboard   /api/v1/patient/dashboard
|     records     /api/v1/patient/records
|     appointment /api/v1/patient/appointment/...
|     vitals      /api/v1/patient/vitals/...
|
| Each module has its own controller and its own service. The dashboard is the
| one that reads across them, because its screen does — it composes the vitals,
| record and appointment modules rather than duplicating them.
|
| Two middleware run over most of this file. `auth:api` is the patient's JWT.
| `tenant` reads the X-Tenant-ID header and makes that hospital current: a
| patient account is shared by the hospitals that registered it, so nothing
| below the header can tell which patient record a request is about. The auth
| and account groups are the exceptions — the first is where the hospital is
| still being chosen and carries it in the body, and the second is about the
| account rather than about any one hospital.
|
*/

Route::group(['prefix' => 'patient'], function () {

    /*
    |----------------------------------------------------------------------
    | Auth Module
    |----------------------------------------------------------------------
    */

    // The patient's own hospitals, looked up from the email or phone number
    // they type — a patient can be registered by more than one of them.
    Route::post('/hospitals', [PatientAuthController::class, 'hospitals']);

    Route::group(['prefix' => 'auth'], function () {
        Route::post('/verify-invitation', [PatientAuthController::class, 'verifyInvitation']);
        Route::post('/create-password', [PatientAuthController::class, 'createPassword']);
        Route::post('/login', [PatientAuthController::class, 'login']);

        // Password reset by one time code rather than an emailed link: the app
        // never leaves itself, so there is no browser to land a link in.
        // Throttled because one endpoint sends mail and the other guesses a six
        // digit code.
        Route::post('/request-reset-password', [PatientAuthController::class, 'requestPasswordReset'])
            ->middleware('throttle:5,10');
        Route::post('/verify-reset-otp', [PatientAuthController::class, 'verifyPasswordResetOtp'])
            ->middleware('throttle:10,10');
        Route::post('/reset-password', [PatientAuthController::class, 'resetPassword']);
    });

    /*
    |----------------------------------------------------------------------
    | Account Module
    |----------------------------------------------------------------------
    |
    | Settings the patient keeps for the account itself rather than for any one
    | hospital, so no tenant middleware here.
    |
    */
    Route::group(['middleware' => ['auth:api']], function () {
        Route::post('/auth/logout', [PatientAuthController::class, 'logout']);
        Route::get('/hospitals/mine', [PatientAuthController::class, 'myHospitals']);
        Route::put('/biometric', [PatientAuthController::class, 'biometric']);
        Route::put('/change-password', [PatientAuthController::class, 'changePassword']);
    });

    /*
    |----------------------------------------------------------------------
    | Hospital scoped modules
    |----------------------------------------------------------------------
    |
    | Everything below is about the patient's record at one hospital, so every
    | group carries the tenant middleware as well as the token.
    |
    */
    Route::group(['middleware' => ['auth:api', 'tenant']], function () {

        /*
        |------------------------------------------------------------------
        | Dashboard Module
        |------------------------------------------------------------------
        */
        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/', [DashboardController::class, 'index']);
        });

        /*
        |------------------------------------------------------------------
        | Health Record Module
        |------------------------------------------------------------------
        |
        | The "My Record" index behind View All. The records themselves belong
        | to the module each row points at — vitals below, laboratory,
        | radiology and admissions as their screens arrive.
        |
        */
        Route::group(['prefix' => 'records'], function () {
            Route::get('/', [RecordController::class, 'index']);
        });

        /*
        |------------------------------------------------------------------
        | Vitals Module
        |------------------------------------------------------------------
        */
        Route::group(['prefix' => 'vitals'], function () {
            // Declared before the /{id} wildcard so it is not read as an id.
            Route::get('/latest', [VitalsController::class, 'latest']);

            Route::get('/', [VitalsController::class, 'index']);
            Route::get('/{id}', [VitalsController::class, 'show'])->whereNumber('id');
        });

        /*
        |------------------------------------------------------------------
        | Laboratory Module
        |------------------------------------------------------------------
        |
        | Read only: results are entered by the lab. The report is rendered from
        | those results on demand, which is what /download streams.
        |
        */
        Route::group(['prefix' => 'laboratory'], function () {
            Route::get('/', [LaboratoryController::class, 'index']);
            Route::get('/{id}', [LaboratoryController::class, 'show'])->whereNumber('id');
            Route::get('/{id}/history', [LaboratoryController::class, 'history'])->whereNumber('id');
            Route::get('/{id}/download', [LaboratoryController::class, 'download'])->whereNumber('id');
        });

        /*
        |------------------------------------------------------------------
        | Radiology Module
        |------------------------------------------------------------------
        */
        Route::group(['prefix' => 'radiology'], function () {
            Route::get('/', [RadiologyController::class, 'index']);
            Route::get('/{id}', [RadiologyController::class, 'show'])->whereNumber('id');
            Route::get('/{id}/history', [RadiologyController::class, 'history'])->whereNumber('id');
            Route::get('/{id}/download', [RadiologyController::class, 'download'])->whereNumber('id');
        });

        /*
        |------------------------------------------------------------------
        | Admission Module
        |------------------------------------------------------------------
        |
        | Read only: a stay is opened, moved and closed off by the ward.
        |
        */
        Route::group(['prefix' => 'admissions'], function () {
            Route::get('/', [AdmissionController::class, 'index']);
            Route::get('/{id}', [AdmissionController::class, 'show'])->whereNumber('id');
        });

        /*
        |------------------------------------------------------------------
        | Profile Module
        |------------------------------------------------------------------
        |
        | Everything written here lands on the patient's record at this
        | hospital. The account itself — the address and password they sign in
        | with, and the hospitals they are linked to — belongs to the auth and
        | account modules above.
        |
        */
        Route::group(['prefix' => 'profile'], function () {
            Route::get('/', [ProfileController::class, 'index']);

            Route::get('/personal-information', [ProfileController::class, 'personalInformation']);
            Route::put('/personal-information', [ProfileController::class, 'updatePersonalInformation']);

            Route::get('/health-information', [ProfileController::class, 'healthInformation']);
            Route::put('/health-information', [ProfileController::class, 'updateHealthInformation']);

            Route::group(['prefix' => 'allergies'], function () {
                Route::get('/', [ProfileController::class, 'allergies']);
                Route::post('/', [ProfileController::class, 'storeAllergy']);
                Route::put('/{id}', [ProfileController::class, 'updateAllergy'])->whereNumber('id');
                Route::delete('/{id}', [ProfileController::class, 'destroyAllergy'])->whereNumber('id');
            });

            Route::group(['prefix' => 'medical-conditions'], function () {
                Route::get('/', [ProfileController::class, 'medicalConditions']);
                Route::post('/', [ProfileController::class, 'storeMedicalCondition']);
                Route::put('/{id}', [ProfileController::class, 'updateMedicalCondition'])->whereNumber('id');
                Route::delete('/{id}', [ProfileController::class, 'destroyMedicalCondition'])->whereNumber('id');
            });

            // Next of kin and emergency contacts are the same four fields kept
            // in two lists, so {type} says which list rather than doubling the
            // routes. It is constrained here as well as validated, so an unknown
            // type is a 404 on the URL rather than reaching the controller.
            Route::group(['prefix' => 'contacts'], function () {
                Route::get('/', [ProfileController::class, 'contacts']);
                Route::post('/{type}', [ProfileController::class, 'storeContact'])
                    ->whereIn('type', ['next_of_kin', 'emergency_contact']);
                Route::put('/{type}/{id}', [ProfileController::class, 'updateContact'])
                    ->whereIn('type', ['next_of_kin', 'emergency_contact'])->whereNumber('id');
                Route::delete('/{type}/{id}', [ProfileController::class, 'destroyContact'])
                    ->whereIn('type', ['next_of_kin', 'emergency_contact'])->whereNumber('id');
            });
        });

        /*
        |------------------------------------------------------------------
        | Appointment Module
        |------------------------------------------------------------------
        */
        Route::group(['prefix' => 'appointment'], function () {

            // Booking flow lookups. Declared before the /{id} routes so their
            // static segments are matched first rather than read as an id.
            Route::get('/consultation-types', [AppointmentController::class, 'consultationTypes']);
            Route::get('/meeting-platforms', [AppointmentController::class, 'meetingPlatforms']);
            Route::get('/departments', [AppointmentController::class, 'departments']);
            Route::get('/departments/{department}/doctors', [AppointmentController::class, 'doctors'])
                ->whereNumber('department');
            Route::get('/doctors/{doctor}/availability', [AppointmentController::class, 'availability'])
                ->whereNumber('doctor');
            Route::get('/doctors/{doctor}/slots', [AppointmentController::class, 'slots'])
                ->whereNumber('doctor');

            Route::get('/', [AppointmentController::class, 'index']);
            Route::post('/', [AppointmentController::class, 'store']);

            Route::get('/{id}', [AppointmentController::class, 'show'])->whereNumber('id');
            Route::put('/{id}', [AppointmentController::class, 'update'])->whereNumber('id');
            Route::put('/{id}/cancel', [AppointmentController::class, 'cancel'])->whereNumber('id');
            Route::put('/{id}/check-in', [AppointmentController::class, 'checkIn'])->whereNumber('id');
            Route::delete('/{id}', [AppointmentController::class, 'destroy'])->whereNumber('id');
        });
    });
});
