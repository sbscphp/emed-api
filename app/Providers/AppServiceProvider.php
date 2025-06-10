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
use App\Repositories\AuditLog\AuditLogInterface;
use App\Repositories\AuditLog\AuditLogRepository;
use App\Repositories\BillingLog\BillingLogRepository;
use App\Repositories\BillingLog\BillingLogRepositoryInterface;
use App\Repositories\Consultation\ConsultationInterface;
use App\Repositories\Consultation\ConsultationRepository;
use App\Repositories\EmergencyContact\EmergencyContactInterface;
use App\Repositories\EmergencyContact\EmergencyContactRepository;
use App\Repositories\Laboratory\LaboratoryInterface;
use App\Repositories\Laboratory\LaboratoryRepository;
use App\Repositories\Medication\MedicationRepository;
use App\Repositories\Medication\MedicationRepositoryInterface;
use App\Repositories\MedicationInventory\MedicationInventoryRepository;
use App\Repositories\MedicationInventory\MedicationInventoryRepositoryInterface;
use App\Repositories\MedicineType\MedicineTypeInterface;
use App\Repositories\MedicineType\MedicineTypeRepository;
use App\Repositories\NextOfKin\NextOfKinInterface;
use App\Repositories\NextOfKin\NextOfKinRepository;
use App\Repositories\Patient\PatientInterface;
use App\Repositories\Patient\PatientRepository;
use App\Repositories\PatientVisit\PatientVisitInterface;
use App\Repositories\PatientVisit\PatientVisitRepository;
use App\Repositories\Radiology\RadiologyInterface;
use App\Repositories\Radiology\RadiologyRepository;
use App\Repositories\Pharmacy\PharmacyInterface;
use App\Repositories\Pharmacy\PharmacyRepository;
use App\Repositories\PharmacyRequest\PharmacyRequestInterface;
use App\Repositories\PharmacyRequest\PharmacyRequestRepository;
use App\Repositories\PharmacySupplier\PharmacySupplyRepository;
use App\Repositories\PharmacySupplier\PharmacySupplyRepositoryInterface;
use App\Repositories\Registration\RegistrationInterface;
use App\Repositories\Registration\RegistrationRepository;
use App\Repositories\Role\RoleInterface;
use App\Repositories\Role\RoleRepository;
use App\Repositories\ServiceDepartment\ServiceDepartmentInterface;
use App\Repositories\ServiceDepartment\ServiceDepartmentRepository;
use App\Repositories\Treatment\TreatmentInterface;
use App\Repositories\Treatment\TreatmentRepository;
use App\Repositories\Triage\TriageInterface;
use App\Repositories\Triage\TriageRepository;
use App\Services\Admission\AdmissionService;
use App\Services\Appointment\AppointmentService;
use App\Services\AuditLog\AuditLogService;
use App\Services\BillingLog\BillingLogService;
use App\Services\Consultation\ConsultationService;
use App\Services\EmergencyContact\EmergencyContactService;
use App\Services\Laboratory\LaboratoryService;
use App\Services\NextOfKin\NextOfKinService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\Radiology\RadiologyService;
use App\Services\Pharmacy\PharmacyService;
use App\Services\Registration\RegistrationService;
use App\Services\ServiceDepartment\ServiceDepartmentService;
use App\Services\Triage\TriageService;
use App\Services\Treatment\TreatmentService;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use App\Services\Medication\MedicationService;
use App\Services\MedicationInventoryService\MedicationInventoryService;
use App\Services\MedicineType\MedicineTypeService;
use App\Services\PharmacyRequest\PharmacyRequestService;
use App\Services\PharmacySupplier\PharmacySupplyService;
use App\Services\Role\RoleService;

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
        $this->app->bind(ConsultationInterface::class, ConsultationRepository::class);
        $this->app->bind(LaboratoryInterface::class, LaboratoryRepository::class);
        $this->app->bind(RadiologyInterface::class, RadiologyRepository::class);
        $this->app->bind(TreatmentInterface::class, TreatmentRepository::class);
        $this->app->bind(PharmacyInterface::class, PharmacyRepository::class);
        $this->app->bind(MedicationRepositoryInterface::class, MedicationRepository::class);
        $this->app->bind(MedicationInventoryRepositoryInterface::class, MedicationInventoryRepository::class);
        $this->app->bind(BillingLogRepositoryInterface::class, BillingLogRepository::class);
        $this->app->bind(AuditLogInterface::class, AuditLogRepository::class);
        $this->app->bind(RoleInterface::class, RoleRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PharmacySupplyRepositoryInterface::class, PharmacySupplyRepository::class);
        $this->app->bind(PharmacyRequestInterface::class, PharmacyRequestRepository::class);
        $this->app->bind(MedicineTypeInterface::class, MedicineTypeRepository::class);


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
        $this->app->bind(ConsultationService::class, function ($app) {
            return new ConsultationService($app->make(ConsultationInterface::class));
        });
        $this->app->bind(LaboratoryService::class, function ($app) {
            return new LaboratoryService($app->make(LaboratoryInterface::class));
        });
        $this->app->bind(RadiologyService::class, function ($app) {
            return new RadiologyService($app->make(RadiologyInterface::class));
        });
        $this->app->bind(TreatmentService::class, function ($app) {
            return new TreatmentService($app->make(TreatmentInterface::class));
        });
        $this->app->bind(PharmacyService::class, function ($app) {
            return new PharmacyService($app->make(PharmacyInterface::class));
        });
        $this->app->bind(MedicationService::class, function ($app) {
            return new MedicationService($app->make(MedicationRepositoryInterface::class));
        });
        $this->app->bind(MedicationInventoryService::class, function ($app) {
            return new MedicationInventoryService($app->make(MedicationInventoryRepositoryInterface::class));
        });
        $this->app->bind(BillingLogService::class, function ($app) {
            return new BillingLogService($app->make(BillingLogRepositoryInterface::class));
        });
        $this->app->bind(AuditLogService::class, function ($app) {
            return new AuditLogService($app->make(AuditLogInterface::class));
        });
        $this->app->bind(RoleService::class, function ($app) {
            return new RoleService($app->make(RoleInterface::class));
        });
        $this->app->bind(UserService::class, function ($app) {
            return new UserService($app->make(UserRepositoryInterface::class));
        });

        $this->app->bind(PharmacySupplyService::class, function ($app) {
            return new PharmacySupplyService($app->make(PharmacySupplyRepositoryInterface::class));
        });

        $this->app->bind(PharmacyRequestService::class, function ($app) {
            return new PharmacyRequestService($app->make(PharmacyRequestInterface::class));
        });

        $this->app->bind(MedicineTypeService::class, function ($app) {
            return new MedicineTypeService($app->make(MedicineTypeInterface::class));
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
