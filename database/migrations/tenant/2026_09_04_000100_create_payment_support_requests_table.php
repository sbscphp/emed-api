<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A patient asking people they trust to help pay one bill.
     *
     * The patient generates a link; anyone holding it can contribute towards
     * that bill, and several people can contribute to the same one. The link
     * carries a random token rather than the bill id, so what a friend receives
     * cannot be edited into somebody else's bill, and it expires — an old link
     * left behind in a group chat stops working.
     *
     * Nothing clinical is stored. A supporter is shown the service the bill is
     * for and the amount, and nothing else about the patient's record.
     */
    public function up(): void
    {
        Schema::create('payment_support_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('billing_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();

            // What goes in the URL. Random rather than derived, so holding one
            // link tells the holder nothing about anybody else's.
            $table->string('token', 64)->unique();

            $table->decimal('target_amount', 12, 2)
                ->comment('What the bill was outstanding when the link was made');
            $table->decimal('raised_amount', 12, 2)->default(0)
                ->comment('Sum of the contributions that verified successfully');

            $table->text('message')->nullable()
                ->comment('An optional note from the patient, shown on the page');

            $table->string('status')->default('Active')
                ->comment('Active, Completed, Expired, Cancelled');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_support_requests');
    }
};
