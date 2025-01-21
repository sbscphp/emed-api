<?php

namespace App;

use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder as SpatieTenantFinder;

class TenantFinder implements SpatieTenantFinder
{
    public function findForRequest(): ?Tenant
    {
        $host = request()->getHost();
        
        return Tenant::where('domain', $host)->first();
    }
}
