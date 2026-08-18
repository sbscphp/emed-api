<?php

use App\Http\Controllers\v1\SuperAdmin\AuditTrail\AuditTrailController;
use App\Http\Controllers\v1\SuperAdmin\ClientManagement\ClientManagementController;
use App\Http\Controllers\v1\SuperAdmin\Configurations\ConfigurationController;
use App\Http\Controllers\v1\SuperAdmin\Dashboard\DashboardController;
use App\Http\Controllers\v1\SuperAdmin\Report\ReportController;
use App\Http\Controllers\v1\SuperAdmin\Subscription\SubscriptionController;
use App\Http\Controllers\v1\SuperAdmin\Usermanagement\UserManagementController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/** Cache for Super Admin */
Route::get('/clear-cache-auth', function () {
    Artisan::call('optimize:clear');
    return "Data Cache is cleared";
});

// Overall Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);

// User Management Routes
Route::prefix('usermanagement')->group(function () {

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserManagementController::class, 'users']);
        Route::post('/create', [UserManagementController::class, 'createUser']);
        Route::get('/{id}', [UserManagementController::class, 'showUser']);
        Route::put('/{id}/update', [UserManagementController::class, 'updateUser']);
        Route::put('/{id}/toggle-status', [UserManagementController::class, 'toggleUserStatus']);
        Route::delete('/{id}', [UserManagementController::class, 'removeUser']);
    });

    // Roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [UserManagementController::class, 'roles']);
        Route::post('/create', [UserManagementController::class, 'createRole']);
        Route::get('/{id}', [UserManagementController::class, 'showRole']);
        Route::put('/update/{id}', [UserManagementController::class, 'updateRole']);
        Route::put('/toggle/{id}', [UserManagementController::class, 'toggleRoleStatus']);
        Route::delete('/delete/{id}', [UserManagementController::class, 'deleteRole']);
    });
});

// Audit Trails
Route::get('/audit-trails', [AuditTrailController::class, 'index']);

// Configurations Routes
Route::prefix('configurations')->group(function () {

    // Usage fees
    Route::prefix('usage-fees')->group(function () {
        Route::get('/', [ConfigurationController::class, 'usageFees']);
        Route::post('/create', [ConfigurationController::class, 'createUsageFee']);
        Route::get('/{id}', [ConfigurationController::class, 'showUsageFee']);
        Route::put('/{id}/update', [ConfigurationController::class, 'updateUsageFee']);
        Route::put('/{id}/toggle-status', [ConfigurationController::class, 'toggleUsageFeeStatus']);
        Route::delete('/{id}', [ConfigurationController::class, 'removeUsageFee']);
    });
});

// Client Management Routes
Route::prefix('clientmanagement')->group(function () {

    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientManagementController::class, 'index']);
        Route::post('/create', [ClientManagementController::class, 'create']);
        Route::get('/{id}', [ClientManagementController::class, 'show']);
        Route::put('/{id}/update', [ClientManagementController::class, 'update']);
        Route::get('/{id}/visit-charges', [ClientManagementController::class, 'showVisitCharges']);
        Route::put('/{id}/update/visit-charges', [ClientManagementController::class, 'updateVisitCharges']);
        Route::put('/{id}/toggle-status', [ClientManagementController::class, 'toggleStatus']);
        Route::delete('/{id}', [ClientManagementController::class, 'remove']);
    });
});

// Subscription Management Routes
Route::prefix('subscriptionmanagement')->group(function () {

    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/plans', [SubscriptionController::class, 'plans']);
        Route::post('/client/usage-fees', [SubscriptionController::class, 'assignClientUsageFee']);
        Route::post('/store', [SubscriptionController::class, 'store']);
        Route::get('/plans/{id}', [SubscriptionController::class, 'showPlan']);
        Route::get('/{id}', [SubscriptionController::class, 'show']);
        Route::put('/{id}/update', [SubscriptionController::class, 'update']);
        Route::put('/{id}/toggle-status', [SubscriptionController::class, 'toggleStatus']);
        Route::delete('/{id}', [SubscriptionController::class, 'remove']);
    });
});

// Report Management Routes
Route::prefix('reportmanagement')->group(function () {

    Route::prefix('reports')->group(function () {
        Route::get('/revenue', [ReportController::class, 'revenueReport']);
        Route::get('/clients', [ReportController::class, 'clientReport']);
        Route::get('/usage-fees', [ReportController::class, 'usageFeeReport']);
    });
});
