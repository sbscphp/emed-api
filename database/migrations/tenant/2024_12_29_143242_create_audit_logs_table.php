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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('causer_id');
            $table->unsignedBigInteger('action_id');
            $table->string('action')->nullable();
            $table->mediumText('action_type')->nullable();
            $table->mediumText('action_module')->nullable();
            $table->string('module_accessed')->nullable();
            $table->mediumText('log_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
