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
        Schema::create('super_admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('module');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('super_admin_permission_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('super_admin_permission_id');
            $table->unsignedBigInteger('super_admin_role_id');
            $table->timestamps();

            $table->foreign('super_admin_permission_id')->references('id')->on('super_admin_permissions')->onDelete('cascade');
            $table->foreign('super_admin_role_id')->references('id')->on('super_admin_roles')->onDelete('cascade');
            $table->unique(['super_admin_permission_id', 'super_admin_role_id'], 'perm_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_permission_role');
        Schema::dropIfExists('super_admin_permissions');
    }
};
