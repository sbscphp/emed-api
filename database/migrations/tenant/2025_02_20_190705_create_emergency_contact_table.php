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
        Schema::create('emergency_contact', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('gender');
            $table->string('phoneno');
            $table->string('stateoforigin')->nullable();
            $table->string('lga')->nullable();
            $table->string('homeaddress')->nullable();
            $table->string('relationship');
            $table->timestamps();
            $table->softDeletes();


            $table->index(['patient_id', 'firstname',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_contact');
    }
};
