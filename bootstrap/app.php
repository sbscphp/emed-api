<?php

use App\Http\Middleware\CurrentTenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use App\Http\Middleware\RecordsAccessMiddleware;
use App\Http\Middleware\NurseAccessMiddleware;
use App\Http\Middleware\ConsultationAccessMiddleware;
use App\Http\Middleware\BillingAccessMiddleware;
use App\Http\Middleware\PharmacyAccessMiddleware;
use App\Http\Middleware\LaboratoryAccessMiddleware;
use App\Http\Middleware\DashboardAccessMiddleware;
use App\Http\Middleware\CheckAdminOrSuperAdmin;
use App\Http\Middleware\SuperAdmin;
use Illuminate\Support\Facades\Route;

// other imports

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api/v1/superadmin')
                ->middleware(["super_admin"])
                ->group(base_path('routes/superadmin/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\SecureHeaders::class);
        $middleware->alias([
            'tenant' => CurrentTenantMiddleware::class,
            'role.record' => RecordsAccessMiddleware::class,
            'role.nurse' => NurseAccessMiddleware::class,
            'role.consultant' => ConsultationAccessMiddleware::class,
            'role.billing' => BillingAccessMiddleware::class,
            'role.pharmacy' => PharmacyAccessMiddleware::class,
            'role.laboratory' => LaboratoryAccessMiddleware::class,
            'role.dashboard' => DashboardAccessMiddleware::class,
            'admin.superadmin' => CheckAdminOrSuperAdmin::class,
            "super_admin" => SuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
