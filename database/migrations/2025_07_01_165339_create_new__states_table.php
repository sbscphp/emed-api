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
        Schema::create('new__states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedMediumInteger('country_id');
            $table->char('country_code', 2);
            $table->string('fips_code')->nullable();
            $table->string('iso2')->nullable();
            $table->string('type', 191)->nullable();
            $table->integer('level')->nullable();
            $table->integer('parent_id')->nullable();
             $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId')->nullable()->comment('Rapid API GeoDB Cities');
            $table->index('country_id', 'country_region');
            $table->foreign('country_id', 'country_region_final')->references('id')->on('countries');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new__states');
    }
};
