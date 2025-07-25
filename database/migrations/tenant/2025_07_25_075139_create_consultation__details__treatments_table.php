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
            $table->enum('select_drug', [
                'paracetamol-tablets',
                'paracetamol-injection',
                'paracetamol syr-syrup',
                'paracetamol-infusion',
                'paramark-infusion'
            ])->nullable();

            $table->enum('qualifier', [
                'tablets',
                'capsule',
                'injection',
                'infusion',
                'creams',
                'syrup'
            ])->nullable();

            $table->enum('dosage', [
                'once daily',
                'twice daily',
                'three time daily',
                'four time daily',
                'nocte'
            ])->nullable();


            $table->enum('weight', [
                'Mg-Milligram',
                'Gm-Grams',
                'Mcg-Mircograms',
                'Mis-Mis',
            ])->nullable();
            $table->string('adherence_period')->nullable();
            $table->string('duration')->nullable();
            $table->enum('route', [
                'oral',
                'mouth',
                'intra-dermal',
                'intra-muscular',
                'sublingual',
                'tropical'
            ])->nullable();
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
