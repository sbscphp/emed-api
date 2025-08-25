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
        Schema::create('patients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('patientno')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('middlename')->nullable();
            $table->date('dob')->nullable();
            $table->string('phoneno')->nullable();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('email')->nullable();
            $table->string('lga')->nullable();
            $table->string('stateoforigin')->nullable();
            $table->string('homeaddress')->nullable();
            $table->string('occupation')->nullable();
            $table->string('religion')->nullable();
            $table->string('tribe')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('cardno')->nullable();
            $table->string('genotype')->nullable();
            $table->string('referral')->nullable();
            $table->string('status')->default('Non-Admitted')->comment('Not-Admitted, Admitted, Discharged, Deceased');
            $table->string('reg_status')->default('New Patient')->comment('New Patient, Follow Up');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
