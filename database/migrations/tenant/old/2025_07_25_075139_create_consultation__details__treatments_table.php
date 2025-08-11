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
        Schema::create('consultation__details__treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->foreignId('patient_visits_id')->nullable()->constrained('patient_visits')->onDelete('cascade');
            $table->string('select_drug')->nullable();
            $table->string('qualifier')->nullable();
            $table->string('dosage')->nullable();
            $table->string('weight')->nullable();
            $table->string('adherence_period')->nullable();
            $table->string('duration')->nullable();
            $table->string('route')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation__details__treatments');
    }
};
