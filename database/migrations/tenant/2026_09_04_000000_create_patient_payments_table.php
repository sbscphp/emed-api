<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every attempt to pay a bill from the patient app or from a shared support
     * link, whether or not it came off.
     *
     * A row is written before the payer leaves for Paystack, not after they come
     * back, because leaving is the only moment we are certain to see.
     * Verification is a separate step against the reference stored here — there
     * is no webhook, so a payment nobody returns from is still recoverable by
     * verifying the row later.
     *
     * The same table carries both a patient paying their own bill and a friend
     * contributing through a support link: it is the same charge, to the same
     * subaccount, against the same bill, and splitting them would mean summing
     * two tables to answer what a bill has been paid.
     */
    public function up(): void
    {
        Schema::create('patient_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('billing_id')->index()
                ->comment('billing_logs.id - the invoice being paid');
            $table->unsignedBigInteger('patient_id')->nullable()->index()
                ->comment('The patient the bill belongs to, set even when a friend pays');
            $table->unsignedBigInteger('support_request_id')->nullable()->index()
                ->comment('Set when this came through a shared support link');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('users.id on the landlord connection; null when a friend paid');

            // Paystack's own reference, generated here so it is known before the
            // payer leaves and can be verified whether or not they return.
            $table->string('reference')->unique();
            $table->string('access_code')->nullable();
            $table->text('authorization_url')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('NGN');
            $table->string('channel')->nullable()->comment('card, bank_transfer');

            $table->string('status')->default('Pending')
                ->comment('Pending, Success, Failed, Abandoned');

            // Who paid. A friend has no account here, so their details come off
            // the checkout form rather than from a user record.
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->boolean('is_anonymous')->default(false)
                ->comment('A supporter who asked not to be named on the progress list');

            // What the hospital was settled and what eMed kept, as Paystack
            // reported it at verification.
            $table->string('subaccount_code')->nullable();
            $table->decimal('hospital_amount', 12, 2)->nullable();
            $table->decimal('platform_amount', 12, 2)->nullable();
            $table->decimal('fees', 12, 2)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable()
                ->comment('When we last asked Paystack what became of this reference');
            $table->text('failure_reason')->nullable();
            $table->json('gateway_response')->nullable()
                ->comment('Paystack verification payload, kept for reconciliation');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['billing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_payments');
    }
};
