<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The doctors that practise in a department.
     *
     * A receptionist booking from the admin schedule picks the department and
     * the doctor independently, so nothing ever had to relate the two. The
     * patient app does: it walks the patient from "Select Department" to
     * "Select your preferred doctor", and that second list has to be the
     * doctors of the department they just chose.
     *
     * Lives on the tenant connection beside departments; user_id points at the
     * landlord users table and so carries no foreign key.
     */
    public function up(): void
    {
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid')->nullable()->index();
            $table->unsignedBigInteger('department_id')->index();
            $table->unsignedBigInteger('user_id')->index()
                ->comment('users.id on the landlord connection');
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);

            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
