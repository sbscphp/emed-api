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
        Schema::create('patient_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->string('visitno');
            $table->enum('stage',['triage','consultation','investigation','admitted','treatment','discharged']);
            $table->enum('status',['ongoing','waiting','completed']);
            $table->dateTime('arrival_date');
            $table->dateTime('departure_date')->nullable();
            $table->dateTime('visit_date')->nullable();
            $table->timestamps();

            $table->index('visitno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visits');
    }
};
