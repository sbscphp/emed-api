<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the access code, now that a payment can be found by it.
     *
     * A checkout hands back two handles: the reference, which Paystack knows the
     * transaction by, and the access code, which the checkout URL ends in and
     * which an inline Paystack popup is resumed with. Verification accepts
     * either, so the access code became a lookup key and needs an index like
     * the reference already has.
     *
     * Not unique: it is Paystack's value rather than ours, and a nullable unique
     * index would only be adding a constraint we cannot guarantee. The index is
     * for the lookup.
     */
    public function up(): void
    {
        Schema::table('patient_payments', function (Blueprint $table) {
            $table->index('access_code');
        });
    }

    public function down(): void
    {
        Schema::table('patient_payments', function (Blueprint $table) {
            $table->dropIndex(['access_code']);
        });
    }
};
