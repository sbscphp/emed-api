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
use App\Repositories\PatientInformation\PatientInformationInterface;
use App\Repositories\PatientInformation\PatientInformationRepository;
use App\Repositories\Registration\RegistrationInterface;
use App\Repositories\Registration\RegistrationRepository;
use App\Services\PatientInformation\PatientInformationService;
use App\Services\Registration\RegistrationService;
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
        $this->app->bind(PatientInformationInterface::class, PatientInformationRepository::class);

        $this->app->bind(UserInformationService::class, function ($app) {
            return new UserInformationService($app->make(UserInformationInterface::class));
        });
        $this->app->bind(UserService::class, function ($app) {
            return new UserService($app->make(UserRepositoryInterface::class));
        });
        $this->app->bind(RegistrationService::class, function ($app) {
            return new RegistrationService($app->make(RegistrationInterface::class));
        });
        $this->app->bind(PatientInformationService::class, function($app){
            return new PatientInformationService($app->make(PatientInformationInterface::class));
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
