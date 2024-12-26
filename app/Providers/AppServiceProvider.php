<?php

namespace App\Providers;

use App\Repositories\UserInformation\UserInformationInterface;
use App\Repositories\UserInformation\UserInformationRepository;
use App\Services\UserInformation\UserInformationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserInformationInterface::class, UserInformationRepository::class);

        $this->app->bind(UserInformationService::class, function ($app) {
            return new UserInformationService($app->make(UserInformationInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
