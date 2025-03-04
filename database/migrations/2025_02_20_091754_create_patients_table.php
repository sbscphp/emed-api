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
            $table->date('dob');
            $table->integer('age');
            $table->string('gender');
            $table->enum('bloodgroup',['A+','A-','B+','B-','AB+','AB-','O+','O-']);
            $table->enum('bloodgenotype',['AA', 'AS', 'SS', 'AC', 'SC', 'CC']);
            $table->string('email')->unique();
            $table->string('patient_type');
            $table->string('marital_status');
            $table->string('phoneno');
            $table->string('occupation');
            $table->string('homeaddress');
            $table->string('companyaddress');
            $table->string('religion')->nullable();
            $table->string('stateoforigin')->nullable();
            $table->string('lga')->nullable();
            $table->string('tribe')->nullable();
            $table->string('cardno')->unique();
            $table->string('recieptno')->unique()->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->dateTime('departure_time')->nullable();
            $table->string('status')->nullable();
            $table->enum('is_active', [false, true])->default(true);
            $table->unsignedBigInteger('service_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('firstname');
            $table->index('lastname');
            $table->index('email');
            $table->index('phoneno');
            $table->index('cardno');
            $table->index('recieptno');
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
