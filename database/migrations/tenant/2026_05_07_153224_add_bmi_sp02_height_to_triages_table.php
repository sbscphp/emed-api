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
        Schema::table('triages', function (Blueprint $table) {
            $table->string('sp02')->nullable()->after('weight_kg');
            $table->string('height')->nullable()->after('sp02');
            $table->string('bmi')->nullable()->after('height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triages', function (Blueprint $table) {
            $table->dropColumn(['sp02', 'height', 'bmi']);
        });
    }
};
