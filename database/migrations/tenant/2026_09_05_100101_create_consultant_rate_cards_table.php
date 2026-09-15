<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-consultant consultation pricing. A row with user_id = null and
     * is_default = true is the shared default used by full-time/salaried doctors
     * (and as a fallback for any doctor without their own card).
     */
    public function up(): void
    {
        Schema::create('consultant_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Consultant (landlord users.id); null = default rate');
            $table->decimal('first_visit_price', 12, 2)->default(0.00);
            $table->decimal('returning_price', 12, 2)->default(0.00);
            $table->string('markup_type')->default('fixed')->comment('fixed, percentage');
            $table->decimal('markup_value', 12, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('Active')->comment('Active, Inactive');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_rate_cards');
    }
};
