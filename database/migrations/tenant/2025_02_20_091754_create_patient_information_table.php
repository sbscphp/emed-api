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
        Schema::create('patient_information', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('firstname');
            $table->string('lastame');
            $table->date('dob');
            $table->integer('age');
            $table->string('gender');
            $table->enum('bloodgroup',['A+','A-','B+','B-','AB+','AB-','O+','O-'])->comment(['A+','A-','B+','B-','AB+','AB-','O+','O-']);
            $table->string('email')->unique();
            $table->string('patient_type');
            $table->string('marital_status');
            $table->string('phoneno');
            $table->string('occupation');
            $table->string('homeaddress');
            $table->string('companyaddress');
            $table->string('religion');
            $table->string('stateoforigin');
            $table->string('lga');
            $table->string('tribe');
            $table->string('cardno')->unique();
            $table->string('receiptno')->unique();
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
        Schema::dropIfExists('patient_information');
    }
};
