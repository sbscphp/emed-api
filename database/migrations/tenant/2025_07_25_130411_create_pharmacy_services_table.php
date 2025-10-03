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
        Schema::create('pharmacy_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('name')->nullable();
            $table->string('active_ingredent')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_services');
    }
};
