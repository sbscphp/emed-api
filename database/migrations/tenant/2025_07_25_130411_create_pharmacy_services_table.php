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
            $table->foreignId('service_unit_id')->nullable()->constrained('service_units')->onDelete('cascade');
            $table->string('active_ingredent')->nullable();
            $table->integer('price')->nullable();
            $table->bigInteger('registration_number')->nullable();
            $table->string('name')->nullable();
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
