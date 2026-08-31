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
        Schema::table('patient_visit_consultation', function (Blueprint $table) {
            $table->longText('documents')->nullable()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_visit_consultation', function (Blueprint $table) {
            $table->dropColumn('documents');
        });
    }
};
