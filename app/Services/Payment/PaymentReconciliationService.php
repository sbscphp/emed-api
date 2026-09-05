<?php

namespace App\Services\Payment;

use App\Enums\GeneralEnums;
use App\Enums\PaymentGatewayEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\BillingLog;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for applying money to a billing.
 *
 * Every payment — manual bank transfer/cash recorded by staff, or a Paystack card
 * payment made by the patient — flows through here so the ledger
 * (`payment_transactions`) and the invoice (`billing_logs`/`billing_log_details`)
 * always stay in agreement, and so a payment can never be applied twice.
 */
class PaymentReconciliationService
{
    /**
     * Distribute a paid amount across a billing's outstanding line items (oldest
     * first) and recompute the header totals/status. Returns the fresh billing.
     */
    public function applyAmount(BillingLog $billing, float $amount): BillingLog
    {
        $remaining = $amount;

        foreach ($billing->billingLogDetails()->orderBy('id')->get() as $detail) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = (float) $detail->amount - (float) $detail->amount_paid;
            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            $detail->amount_paid = (float) $detail->amount_paid + $applied;
            $detail->status = $this->statusFor($detail->amount_paid, $detail->amount);
            $detail->save();

            $remaining -= $applied;
        }

        $itemsTotal = (float) $billing->billingLogDetails()->sum('amount');
        $paidTotal  = (float) $billing->billingLogDetails()->sum('amount_paid');
        $discount   = (float) ($billing->discount ?? 0);

        $billing->grand_total        = max(0, $itemsTotal - $discount);
        $billing->amount_paid        = min($paidTotal, $billing->grand_total);
        $billing->amount_outstanding = max(0, $billing->grand_total - $billing->amount_paid);
        $billing->payment_status     = $this->statusFor($billing->amount_paid, $billing->grand_total);
        $billing->total_amount       = (float) $billing->amount_paid + (float) ($billing->tax_amount ?? 0);
        $billing->save();

        return $billing->fresh(['patient', 'billingLogDetails', 'transactions']);
    }

    /**
     * Record a completed manual payment (transfer/cash/pos) and apply it.
     * Wrapped in a tenant transaction so the ledger row and the applied amount
     * are all-or-nothing.
     */
    public function recordManualPayment(BillingLog $billing, float $amount, string $channel, array $context = []): PaymentTransaction
    {
        return DB::connection('tenant')->transaction(function () use ($billing, $amount, $channel, $context) {
            $transaction = PaymentTransaction::create([
                'tenant_id'    => $billing->tenant_id,
                'billing_id'   => $billing->id,
                'patient_id'   => $billing->patient_id,
                'amount'       => $amount,
                'currency'     => 'NGN',
                'channel'      => $channel,
                'gateway'      => PaymentGatewayEnum::MANUAL->value,
                'reference'    => $context['reference'] ?? $this->reference('MAN', $billing->id),
                'status'       => TransactionStatusEnum::SUCCESS->value,
                'paid_at'      => now(),
                'initiated_by' => 'staff',
                'created_by'   => $context['created_by'] ?? null,
                'metadata'     => $context['metadata'] ?? null,
            ]);

            $this->applyAmount($billing, $amount);

            return $transaction->fresh('billingLog');
        });
    }

    /**
     * Create the pending ledger row for a card payment before the patient is sent
     * to Paystack. The reference returned is what Paystack echoes back and what the
     * webhook / verify-on-return use to reconcile.
     */
    public function initializeGatewayTransaction(BillingLog $billing, float $amount, array $metadata, ?int $createdBy = null): PaymentTransaction
    {
        return PaymentTransaction::create([
            'tenant_id'    => $billing->tenant_id,
            'billing_id'   => $billing->id,
            'patient_id'   => $billing->patient_id,
            'amount'       => $amount,
            'currency'     => 'NGN',
            'channel'      => \App\Enums\PaymentChannelEnum::CARD->value,
            'gateway'      => PaymentGatewayEnum::PAYSTACK->value,
            'reference'    => $this->reference('PSK', $billing->id),
            'status'       => TransactionStatusEnum::PENDING->value,
            'initiated_by' => 'patient',
            'created_by'   => $createdBy,
            'metadata'     => $metadata,
        ]);
    }

    /**
     * Reconcile a gateway transaction by reference. Idempotent: if the transaction
     * is already successful, it is returned untouched (a duplicate webhook or a
     * verify-after-webhook cannot double-credit the bill). The verified amount from
     * the gateway is authoritative.
     */
    public function reconcile(string $reference, array $gatewayPayload): PaymentTransaction
    {
        return DB::connection('tenant')->transaction(function () use ($reference, $gatewayPayload) {
            /** @var PaymentTransaction|null $transaction */
            $transaction = PaymentTransaction::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new \RuntimeException("Unknown payment reference: {$reference}");
            }

            // Already reconciled — no-op (idempotency guard).
            if ($transaction->status === TransactionStatusEnum::SUCCESS->value) {
                return $transaction;
            }

            $gatewayStatus = strtolower($gatewayPayload['status'] ?? '');
            if ($gatewayStatus !== 'success') {
                $transaction->status = TransactionStatusEnum::FAILED->value;
                $transaction->gateway_reference = $gatewayPayload['reference'] ?? $transaction->gateway_reference;
                $transaction->metadata = array_merge((array) $transaction->metadata, ['gateway' => $gatewayPayload]);
                $transaction->save();

                return $transaction;
            }

            // Paystack amounts are in kobo — convert back to naira.
            $verifiedAmount = isset($gatewayPayload['amount'])
                ? ((float) $gatewayPayload['amount']) / 100
                : (float) $transaction->amount;

            $transaction->amount = $verifiedAmount;
            $transaction->status = TransactionStatusEnum::SUCCESS->value;
            $transaction->paid_at = now();
            $transaction->gateway_reference = $gatewayPayload['reference'] ?? $transaction->gateway_reference;
            $transaction->metadata = array_merge((array) $transaction->metadata, ['gateway' => $gatewayPayload]);
            $transaction->save();

            $billing = BillingLog::findOrFail($transaction->billing_id);
            $this->applyAmount($billing, $verifiedAmount);

            return $transaction->fresh('billingLog');
        });
    }

    private function statusFor(float $paid, float $total): string
    {
        if ($paid <= 0) {
            return GeneralEnums::PENDING->value;
        }

        return $paid >= $total ? GeneralEnums::PAID->value : GeneralEnums::PART_PAID->value;
    }

    private function reference(string $prefix, int $billingId): string
    {
        return sprintf('%s-%d-%s', $prefix, $billingId, strtoupper(Str::random(10)));
    }
}
