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
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('address');
            $table->string('email_address')->nullable()->after('phone_number');
            $table->time('opening_time')->nullable()->after('email_address');
            $table->time('closing_time')->nullable()->after('opening_time');
            $table->unsignedBigInteger('assigned_pharmacist')->nullable()->after('closing_time');
            $table->string('license_number')->nullable()->after('assigned_pharmacist');
            $table->string('pharmacy_id')->unique()->after('license_number');

            $table->foreign('assigned_pharmacist')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pharmacies');
    }
};
