<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A billing service is now a sub-service of a service: GOPD owns "General
     * consultation", "Pediatric consultation" and the rest. The parent is
     * required, the department it is delivered in is optional, and the service
     * unit — which the parent service replaces — stops being required.
     */
    public function up(): void
    {
        Schema::table('billing_services', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_services', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('tenant_id')->index();
            }

            if (!Schema::hasColumn('billing_services', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('service_id')->index();
            }
        });

        $this->backfillParentServices();

        // Only once every existing row has a parent can the column be closed.
        Schema::table('billing_services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->change();
        });

        Schema::table('billing_services', function (Blueprint $table) {
            if (Schema::hasColumn('billing_services', 'service_id')) {
                $table->dropColumn('service_id');
            }

            if (Schema::hasColumn('billing_services', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }

    /**
     * Give every billing service already in the table a parent service.
     *
     * The category it was filed under ("Admission", "Consultation", "General")
     * is the closest thing to a parent it has, so that becomes the service it
     * hangs off — created for the tenant if it is not there already.
     *
     * @return void
     */
    protected function backfillParentServices(): void
    {
        $rows = DB::table('billing_services')
            ->whereNull('service_id')
            ->select('tenant_id', 'category')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) $row->category) !== '' ? trim($row->category) : 'General';

            $service = DB::table('services')
                ->where('name', $name)
                ->when($row->tenant_id, fn($q) => $q->where('tenant_id', $row->tenant_id))
                ->when(!$row->tenant_id, fn($q) => $q->whereNull('tenant_id'))
                ->first();

            $serviceId = $service->id ?? DB::table('services')->insertGetId([
                'tenant_id' => $row->tenant_id,
                'name' => $name,
                'price' => 0.00,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('billing_services')
                ->whereNull('service_id')
                ->when($row->tenant_id, fn($q) => $q->where('tenant_id', $row->tenant_id))
                ->when(!$row->tenant_id, fn($q) => $q->whereNull('tenant_id'))
                ->when(trim((string) $row->category) !== '', fn($q) => $q->where('category', $row->category))
                ->when(trim((string) $row->category) === '', function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNull('category')->orWhere('category', '');
                    });
                })
                ->update(['service_id' => $serviceId]);
        }
    }
};
