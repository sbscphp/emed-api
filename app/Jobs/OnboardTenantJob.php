<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\Registration;
use App\Responser\JsonResponser;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class OnboardTenantJob implements ShouldQueue
{
    use Queueable;

    protected $tenantId;
    protected RegistrationService $registrationService;
    public function __construct(RegistrationService $registrationService, $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->registrationService = $registrationService;
    }

    public function handle()
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $data = $tenant->toArray();
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $tenant->makeCurrent();

            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            // Run migrations for tenant database
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Optionally, create admin user for the tenant if not already done
            $adminData = [
                'uuid' => Str::uuid(),
                'fullname' => $data['admin_fullname'],
                'role' => $data['admin_role'],
                'phone_number' => $data['admin_phone_number'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'tenant_id' => $tenant->id,
            ];

            // Save admin data logic here...
            $admin = $this->registrationService->saveAdminDetails($adminData, $tenant->id);
        } catch (\Exception $e) {
            return JsonResponser::send(
                false,
                'An error occurred during tenant onboarding: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
