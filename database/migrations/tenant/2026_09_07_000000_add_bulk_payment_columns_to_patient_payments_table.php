<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One checkout that clears every outstanding bill at once.
     *
     * The "Pay now" button on the Outstanding Bills card is a single charge
     * against several invoices, which the table could not describe: billing_id
     * holds exactly one. It is kept as the invoice the checkout was raised
     * against — every existing query, index and relationship still reads it —
     * and the rest of the set is carried alongside it.
     *
     * Two columns rather than one, because a bulk charge has to answer two
     * different questions. `billing_ids` is what the payer set out to pay, known
     * before they leave for Paystack. `allocations` is what each invoice
     * actually received, known only once the charge is confirmed and the money
     * is spread across the bills oldest first — without it, a bill's own detail
     * screen and receipt would print the whole 85,000 against an invoice that
     * only took 35,000 of it.
     */
    public function up(): void
    {
        Schema::table('patient_payments', function (Blueprint $table) {
            $table->json('billing_ids')->nullable()->after('billing_id')
                ->comment('billing_logs.id list a bulk checkout covers, oldest first; null for a single bill');

            $table->json('allocations')->nullable()->after('billing_ids')
                ->comment('billing_logs.id => amount actually applied, written when the charge is confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('patient_payments', function (Blueprint $table) {
            $table->dropColumn(['billing_ids', 'allocations']);
        });
    }
};
