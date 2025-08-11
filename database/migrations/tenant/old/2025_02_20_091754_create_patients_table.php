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
            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->date('dob')->nullable();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('genotype')->nullable();
            $table->string('email')->nullable();
            $table->string('patient_type')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('phoneno')->nullable();
            $table->string('occupation')->nullable();
            $table->string('homeaddress')->nullable();
            $table->string('companyaddress')->nullable();
            $table->string('religion')->nullable();
            $table->string('stateoforigin')->nullable();
            $table->string('lga')->nullable();
            $table->string('tribe')->nullable();
            $table->string('cardno')->nullable();
            $table->string('recieptno')->nullable();
            $table->string('status')->nullable();
            $table->enum('reg_status', [0, 1])->default(1);
            $table->longText('image')->nullable();
            $table->string('patientno')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->integer('follow_up')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('id');
            $table->index('firstname');
            $table->index('lastname');
            $table->index('patientno');
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
