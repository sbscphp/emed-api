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
        Schema::create('consultation__details__radiologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->foreignId('patient_visits_id')->nullable()->constrained('patient_visits')->onDelete('cascade');
            // $table->string('laborartory_department')->nullable();
            // $table->string('laborartory_test')->nullable();
            $table->enum('laborartory_department', [
                'x-ray',
                'scan',
                'special scan',
                'ultrasound'
            ])->nullable();

            $table->enum('laborartory_test', [
                'abdomen supine/erect',
                'both elbow',
                'both elbow joint ap/lat',
                'both ankle ap./lat',
                'knee ap./lat',
                'cervical spine lat only'
            ])->nullable();
            $table->string('other_laborartory_test')->nullable();
            $table->string('ordered_test')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation__details__radiologies');
    }
};
