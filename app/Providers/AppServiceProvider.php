<?php

namespace App\Providers;

use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use App\Repositories\UserInformation\UserInformationInterface;
use App\Repositories\UserInformation\UserInformationRepository;
use App\Services\User\UserService;
use App\Services\UserInformation\UserInformationService;
use Illuminate\Support\ServiceProvider;
use App\Models\Sanctum\PersonalAccessToken;
use App\Models\Service;
use App\Repositories\Admission\AdmissionInterface;
use App\Repositories\Admission\AdmissionRepository;
use App\Repositories\Appointment\AppointmentInterface;
use App\Repositories\Appointment\AppointmentRepository;
use App\Repositories\EmergencyContact\EmergencyContactInterface;
use App\Repositories\EmergencyContact\EmergencyContactRepository;
use App\Repositories\NextOfKin\NextOfKinInterface;
use App\Repositories\NextOfKin\NextOfKinRepository;
use App\Repositories\Patient\PatientInterface;
use App\Repositories\Patient\PatientRepository;
use App\Repositories\PatientVisit\PatientVisitInterface;
use App\Repositories\PatientVisit\PatientVisitRepository;
use App\Repositories\Registration\RegistrationInterface;
use App\Repositories\Registration\RegistrationRepository;
use App\Repositories\ServiceDepartment\ServiceDepartmentInterface;
use App\Repositories\ServiceDepartment\ServiceDepartmentRepository;
use App\Repositories\Triage\TriageInterface;
use App\Repositories\Triage\TriageRepository;
use App\Services\Admission\AdmissionService;
use App\Services\Appointment\AppointmentService;
use App\Services\EmergencyContact\EmergencyContactService;
use App\Services\NextOfKin\NextOfKinService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\Registration\RegistrationService;
use App\Services\ServiceDepartment\ServiceDepartmentService;
use App\Services\Triage\TriageService;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserInformationInterface::class, UserInformationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RegistrationInterface::class, RegistrationRepository::class);
        $this->app->bind(PatientInterface::class, PatientRepository::class);
        $this->app->bind(NextOfKinInterface::class, NextOfKinRepository::class);
        $this->app->bind(EmergencyContactInterface::class, EmergencyContactRepository::class);
        $this->app->bind(ServiceDepartmentInterface::class, ServiceDepartmentRepository::class);
        $this->app->bind(AdmissionInterface::class, AdmissionRepository::class);
        $this->app->bind(AppointmentInterface::class, AppointmentRepository::class);
        $this->app->bind(PatientVisitInterface::class, PatientVisitRepository::class);
        $this->app->bind(TriageInterface::class, TriageRepository::class);


        $this->app->bind(UserInformationService::class, function ($app) {
            return new UserInformationService($app->make(UserInformationInterface::class));
        });
        $this->app->bind(UserService::class, function ($app) {
            return new UserService($app->make(UserRepositoryInterface::class));
        });
        $this->app->bind(RegistrationService::class, function ($app) {
            return new RegistrationService($app->make(RegistrationInterface::class));
        });
        $this->app->bind(PatientService::class, function ($app) {
            return new PatientService($app->make(PatientInterface::class));
        });
        $this->app->bind(NextOfKinService::class, function ($app) {
            return new NextOfKinService($app->make(NextOfKinInterface::class));
        });
        $this->app->bind(EmergencyContactService::class, function ($app) {
            return new EmergencyContactService($app->make(EmergencyContactInterface::class));
        });
        $this->app->bind(ServiceDepartmentService::class, function ($app) {
            return new ServiceDepartmentService($app->make(ServiceDepartmentInterface::class));
        });
        $this->app->bind(AdmissionService::class, function ($app) {
            return new AdmissionService($app->make(AdmissionInterface::class));
        });
        $this->app->bind(AppointmentService::class, function ($app) {
            return new AppointmentService($app->make(AppointmentInterface::class));
        });
        $this->app->bind(PatientVisitService::class, function ($app) {
            return new PatientVisitService($app->make(PatientVisitInterface::class));
        });
        $this->app->bind(TriageService::class, function ($app) {
            return new TriageService($app->make(TriageInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(SanctumPersonalAccessToken::class);
    }
}
