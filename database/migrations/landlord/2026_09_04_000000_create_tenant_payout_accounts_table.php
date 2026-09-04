<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a hospital's share of a patient payment is settled to.
     *
     * Patients pay eMed's Paystack account, so the hospital needs somewhere to
     * be paid out from it. That is a Paystack subaccount: the bank details are
     * pushed to Paystack once, and every charge raised for this hospital is
     * initialized against the `subaccount_code` returned. Paystack then splits
     * at settlement, sending the hospital's share to their bank directly and
     * leaving eMed's commission behind.
     *
     * On the landlord connection rather than in the hospital's own database,
     * because it is the platform that has to read every hospital's payout
     * arrangement — for reconciliation, and for support when a settlement
     * question arrives.
     *
     * No bank *credential* is held here, only the account a settlement is sent
     * to, which is the same information a hospital prints on an invoice.
     */
    public function up(): void
    {
        Schema::create('tenant_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();

            // Resolved against Paystack before saving, so account_name is the
            // name the bank returned rather than one typed by hand.
            $table->string('bank_code');
            $table->string('bank_name')->nullable();
            $table->string('account_number');
            $table->string('account_name');

            $table->string('business_name')->nullable()
                ->comment('What the payer sees on their statement; defaults to the hospital name');
            $table->string('subaccount_code')->nullable()->index()
                ->comment('Paystack subaccount, e.g. ACCT_xxxxxxxx');

            // eMed's cut. Paystack is told the inverse of this — the share the
            // hospital keeps — so only one number is ever stored.
            $table->decimal('commission_percent', 5, 2)->default(0);

            $table->string('status')->default('Pending')
                ->comment('Pending, Active, Failed');
            $table->text('last_error')->nullable()
                ->comment('Why the last push to Paystack failed, for the hospital to act on');
            $table->timestamp('verified_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payout_accounts');
    }
};
