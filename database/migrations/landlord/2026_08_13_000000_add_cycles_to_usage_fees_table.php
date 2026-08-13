<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('usage_fees', function (Blueprint $table) {
            if (!Schema::connection('landlord')->hasColumn('usage_fees', 'cycles')) {
                $table->json('cycles')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('usage_fees', function (Blueprint $table) {
            if (Schema::connection('landlord')->hasColumn('usage_fees', 'cycles')) {
                $table->dropColumn('cycles');
            }
        });
    }
};
