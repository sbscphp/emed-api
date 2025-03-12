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
        Schema::create('patient_visit_radiology', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('delete');
            $table->foreignId('admin_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('visitno');
            $table->string('lab_dept')->nullable();
            $table->string('test_name')->nullable();
            $table->string('ordered_test')->nullable();
            $table->string('others')->nullable();
            $table->timestamps();

            $table->index('visitno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visit_radiology');
    }
};
