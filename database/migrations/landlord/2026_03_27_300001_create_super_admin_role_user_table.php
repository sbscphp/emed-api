<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('super_admin_role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('super_admin_role_id')->references('id')->on('super_admin_roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['super_admin_role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_role_user');
    }
};
