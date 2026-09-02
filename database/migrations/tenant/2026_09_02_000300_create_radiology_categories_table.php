<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imaging modalities a radiology service belongs to.
     *
     * Kept separate from service_categories, which holds laboratory
     * disciplines: the two lists never overlap, and sharing one table would
     * mean every category picker had to know which half to filter to.
     *
     * No tenant_id, for the same reason service_categories has none — the table
     * already lives inside the tenant's own database.
     */
    public function up(): void
    {
        Schema::create('radiology_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_categories');
    }
};
