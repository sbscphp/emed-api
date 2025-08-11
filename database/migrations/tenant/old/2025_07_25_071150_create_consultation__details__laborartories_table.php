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
        Schema::create('consultation__details__laborartories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->foreignId('patient_visits_id')->nullable()->constrained('patient_visits')->onDelete('cascade');
            $table->enum('laborartory_dept', [
                'bacteriology',
                'chemical pathology',
                'heamatology',
                'parasitology',
                'anc',
                'other test'
            ])->nullable();
            $table->enum('laborartory_test', [
                'mircoscopic culture sensitivity',
                'serology',
                'microscopy',
                'widal test',
                'semen analysis',
                'skin snip test'
            ])->nullable();
            $table->string('other_laborartory')->nullable();
            $table->string('order_test')->nullable();
            $table->string('test_status')->default('Not Ready')->comment('Not Ready', 'Ready');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation__details__laborartories');
    }
};
