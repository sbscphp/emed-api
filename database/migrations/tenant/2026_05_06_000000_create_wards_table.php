<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->enum('type', ['Children', 'Adult', 'General']);
            $table->enum('gender', ['Male', 'Female', 'Unisex']);
            $table->boolean('status')->default(true);
            $table->decimal('bed_cost', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('wards');
    }
};
