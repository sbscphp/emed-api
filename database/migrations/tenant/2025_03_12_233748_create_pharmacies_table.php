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
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assigned_pharmacist')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('pharmacy_id')->unique();
            $table->string('name');
            $table->string('license_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email_address')->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->string('address')->nullable();
            $table->string('type')->nullable()->comment('main', 'sub');
            $table->boolean('active')->default(true)->comment(true, false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
