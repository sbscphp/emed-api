<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('bed_number');
            $table->unsignedInteger('number_of_available')->default(1);
            $table->boolean('occupied')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ward_id', 'bed_number']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('beds');
    }
};
