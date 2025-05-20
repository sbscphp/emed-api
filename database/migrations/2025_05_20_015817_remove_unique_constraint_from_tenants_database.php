<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropUnique(['database']);
        });
    }

    public function down()
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->unique('database');
        });
    }
};
