<?php

namespace App\Tasks;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Exceptions\InvalidConfiguration;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class ConditionalSwitchTenantDatabaseTask implements SwitchTenantTask
{
    protected ?string $originalDefaultConnectionName = null;

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->originalDefaultConnectionName = DB::getDefaultConnection();

        $tenantConnectionName = $this->getTenantConnectionName();
        $landlordConnectionName = $this->getLandlordConnectionName();

        if ($tenantConnectionName === $landlordConnectionName) {
            throw InvalidConfiguration::tenantConnectionIsEmptyOrEqualsToLandlordConnection();
        }

        $databaseName = $this->resolveTenantDatabaseName($tenant);

        if (is_null($databaseName)) {
            // Optional: Throw an exception or handle cases where DB name is needed but missing
            // throw new \Exception("Database name for tenant {$tenant->getKey()} could not be resolved in environment " . app()->environment());
            // Or maybe default to landlord in specific non-prod cases if desired? Carefully consider implications.
            $this->switchToLandlordConnection(); // Example: Fallback safely if needed
            return;
        }

        // Set the database name for the 'tenant' connection configuration
        Config::set("database.connections.{$tenantConnectionName}.database", $databaseName);

        // Set the default connection to the tenant connection
        DB::setDefaultConnection($tenantConnectionName);

        // Purge the tenant connection to force Laravel to reconnect using the new settings
        DB::purge($tenantConnectionName);

        // Optionally reconnect to ensure the connection uses the new config immediately
        // DB::reconnect($tenantConnectionName); // Usually purge is enough, uncomment if needed
    }

    public function forgetCurrent(): void
    {
        $tenantConnectionName = $this->getTenantConnectionName();

        // Reset the database name in the config (optional, good practice)
        Config::set("database.connections.{$tenantConnectionName}.database", null);

        // Purge the tenant connection
        DB::purge($tenantConnectionName);

        // Restore the original default connection
        if ($this->originalDefaultConnectionName) {
            DB::setDefaultConnection($this->originalDefaultConnectionName);
            $this->originalDefaultConnectionName = null;
        }
    }

    protected function resolveTenantDatabaseName(IsTenant $tenant): ?string
    {
        $environment = app()->environment(); // Get current environment (e.g., 'local', 'production', 'development', 'uat')

        if (in_array($environment, ['local', 'production'])) {
            // In local/production, use the database name stored on the tenant model
            // Ensure your Tenant model has a 'database' attribute/column configured in multitenancy.php
            return $tenant->getDatabaseName(); // Assumes Tenant model has getDatabaseName() or 'database' attribute
        } elseif ($environment === 'development' || $environment === 'dev') {
            // In development, use the predefined shared development database name
            return env('DEV_TENANT_DB_DATABASE');
            // Or directly use env('DEV_TENANT_DB_DATABASE') if preferred
        } elseif ($environment === 'uat') {
            // In UAT, use the predefined shared UAT database name
            return config('database.connections.tenant.database') ?? env('STAGING_TENANT_DB_DATABASE');
            // Or directly use env('STAGING_TENANT_DB_DATABASE') if preferred
        } else {
            // Handle other environments or return null/throw exception if unsupported
            // Returning null might cause issues if a DB connection is expected.
            // Consider throwing an exception for unhandled environments.
            report("Unhandled application environment '{$environment}' in ConditionalSwitchTenantDatabaseTask.");
            return null; // Or throw new \RuntimeException("Unsupported environment: {$environment}");
        }
    }

    /**
     * Switches the default database connection back to the landlord connection.
     * Useful as a safe fallback.
     */
    protected function switchToLandlordConnection(): void
    {
        $landlordConnectionName = $this->getLandlordConnectionName();
        if ($landlordConnectionName && DB::getDefaultConnection() !== $landlordConnectionName) {
            DB::setDefaultConnection($landlordConnectionName);
        }
    }

    protected function getTenantConnectionName(): string
    {
        return config('multitenancy.tenant_database_connection_name');
    }

    protected function getLandlordConnectionName(): ?string
    {
        return config('multitenancy.landlord_database_connection_name');
    }
}
