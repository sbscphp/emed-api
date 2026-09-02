<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short lived tokens handed out when a patient confirms the invitation we
     * found for them, and spent by the "create password" screen.
     *
     * This is deliberately not password_reset_tokens: that table is keyed by
     * email, so an invitation would clobber a password reset the same patient
     * had already requested (and vice versa). It is also tenant scoped, since a
     * patient can hold an invitation from more than one hospital.
     */
    public function up(): void
    {
        Schema::create('patient_verification_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('patient_no')->nullable();
            $table->string('token', 128)->unique();
            $table->string('purpose')->default('invitation')->comment('invitation');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_verification_tokens');
    }
};
