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
        Schema::create('subregions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('translations')->nullable();
            $table->unsignedMediumInteger('region_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId')->nullable()->comment('Rapid API GeoDB Cities');
            $table->index('region_id', 'subregion_continent');
            $table->foreign('region_id', 'subregion_continent_final')->references('id')->on('regions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subregions');
    }
};
