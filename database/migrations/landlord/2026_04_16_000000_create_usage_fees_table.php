<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('usage_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_general_visit')->default(false);
            $table->boolean('is_unique_visit')->default(false);
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('usage_fees');
    }
};
