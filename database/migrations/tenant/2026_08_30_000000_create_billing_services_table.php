<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_services', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->string('code')->nullable()->index()
                ->comment('Stable lookup key, e.g. ADMISSION, CONSULTATION');
            $table->string('category')->nullable()->index()
                ->comment('Admission, Consultation, General');
            $table->string('name')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_services');
    }
};
