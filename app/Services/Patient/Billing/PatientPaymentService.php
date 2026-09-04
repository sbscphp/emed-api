<?php

namespace App\Services\Patient\Billing;

use App\Exceptions\PatientAppException;
use App\Models\BillingLog;
use App\Models\PatientPayment;
use App\Models\PaymentSupportRequest;
use App\Models\Tenant;
use App\Services\Billing\HospitalPayoutAccountService;
use App\Services\Patient\Notification\PatientNotificationService;
use App\Services\Patient\PatientContextService;
use App\Services\Payment\PaystackException;
use App\Services\Payment\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class PatientPaymentService
 *
 * Paying a bill: starting a checkout, and settling what came of it.
 *
 * The flow is deliberately two calls rather than one. Starting a payment writes
 * a row and hands back a Paystack page; confirming it is a separate request
 * against the reference on that row. There is no webhook, so the reference is
 * the only durable handle on a charge — which is exactly why it is generated
 * here and stored before the payer leaves. A patient who pays and then loses
 * their connection is recovered by verifying the same reference again, and
 * verification is idempotent: the second call finds the bill already credited
 * and answers with the same result rather than paying it twice.
 *
 * Money is only ever moved onto a bill inside verify(), and only on Paystack's
 * word. Nothing trusts the redirect the payer came back on.
 */
class PatientPaymentService
{
    public function __construct(
        protected PatientContextService $context,
        protected PatientBillingService $billing,
        protected PaystackService $paystack,
        protected HospitalPayoutAccountService $payoutAccounts,
        protected PatientNotificationService $notifications,
    ) {}

    /**
     * Start a checkout for one of the signed in patient's bills.
     *
     * @param  int  $billId
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function initiate($billId, array $data = []): array
    {
        $tenant = $this->context->tenant();
        $patient = $this->context->patient();
        $user = $this->context->user();

        $bill = $this->billing->findOwnedBill($billId);
        $outstanding = $this->billing->outstandingFor($bill);

        if ($outstanding <= 0) {
            throw new PatientAppException('This bill has already been paid.', 409);
        }

        // A part payment is allowed; paying more than is owed is not, because
        // the overpayment would have to be refunded out of band.
        $amount = isset($data['amount']) && $data['amount'] !== null
            ? round((float) $data['amount'], 2)
            : $outstanding;

        if ($amount <= 0) {
            throw new PatientAppException('Enter an amount to pay.', 422);
        }

        if ($amount > $outstanding) {
            throw new PatientAppException(
                'That is more than this bill is outstanding. The most you can pay is ' . number_format($outstanding, 2) . '.',
                422
            );
        }

        $email = $data['email'] ?? $user->email ?? $patient->email;

        if (empty($email)) {
            throw new PatientAppException('Your account needs an email address before you can pay online.', 422);
        }

        return $this->startCheckout(
            tenant: $tenant,
            bill: $bill,
            amount: $amount,
            email: $email,
            payerName: trim($user->fullname ?: "{$patient->firstname} {$patient->lastname}"),
            attributes: [
                'patient_id' => $patient->id,
                'user_id' => $user->id,
            ],
            channels: $data['channels'] ?? null,
        );
    }

    /**
     * Ask Paystack what became of a reference, and settle the bill if it
     * succeeded.
     *
     * The one route the app calls after checkout, and the same one used to
     * recover a payment nobody came back from. Left deliberately callable
     * without the patient's token by the public support flow, which passes the
     * tenant it already resolved from the link.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function verify(string $reference, ?Tenant $tenant = null): array
    {
        // A tenant handed in is the public support flow: there is no signed in
        // patient there, and the caller has already checked that the payment
        // belongs to the link's token. No tenant is the patient's own app, where
        // the payment has to be the signed in patient's own — without that
        // check, a reference is enough to read another patient's invoice number
        // and balance from inside the same hospital.
        $mustBelongToSignedInPatient = $tenant === null;
        $tenant = $tenant ?: $this->context->tenant();

        $payment = $this->findByReference($reference);

        $isTheirs = $payment
            && (!$mustBelongToSignedInPatient
                || (int) $payment->patient_id === (int) $this->context->patient()->id);

        if (!$isTheirs) {
            // Answered as "not found" rather than "not yours" on purpose: the
            // two must not be distinguishable, or the endpoint becomes a way to
            // test whether a reference exists.
            //
            // Logged as well as refused: a caller holding a handle we do not
            // recognise cannot be told why from the app's error line, and this
            // is the one place that knows what was asked for.
            Log::warning('A payment was verified against an unknown or foreign handle.', [
                'tenant_id' => $tenant->id,
                'handle' => $reference,
                'found' => (bool) $payment,
            ]);

            throw new PatientAppException('We could not find that payment.', 404);
        }

        // Already settled. Answering with the stored result rather than asking
        // Paystack again is what makes a retried verification safe.
        if ($payment->status === PatientPayment::SUCCESS) {
            return $this->result($payment, alreadySettled: true);
        }

        try {
            // Always the stored reference, never what the caller passed —
            // Paystack knows a transaction by its reference alone, and the
            // caller may have handed us the access code instead.
            $response = $this->paystack->verifyTransaction($payment->reference);
        } catch (PaystackException $th) {
            throw new PatientAppException($th->getMessage(), $th->getCode() === 503 ? 503 : 502);
        }

        $data = $response['data'] ?? [];
        $gatewayStatus = strtolower($data['status'] ?? '');

        if ($gatewayStatus !== 'success') {
            $payment->forceFill([
                'status' => $gatewayStatus === 'abandoned' ? PatientPayment::ABANDONED : PatientPayment::FAILED,
                'channel' => $data['channel'] ?? $payment->channel,
                'failure_reason' => $data['gateway_response'] ?? 'The payment was not completed.',
                'gateway_response' => $data,
                'verified_at' => now(),
            ])->save();

            return $this->result($payment->fresh());
        }

        return $this->settle($tenant, $payment, $data);
    }

    /**
     * The payment a caller means, by either handle it could be holding.
     *
     * A checkout hands back two: the `reference`, which is what Paystack knows
     * the transaction by and what verification needs, and the `access_code`,
     * which is what the checkout URL ends in and what an inline Paystack popup
     * is resumed with. Both are ours, both are unique, and both point at exactly
     * one payment — so both are accepted here rather than making the caller
     * remember which of the two this endpoint wanted.
     *
     * Reference is tried first: it is the documented handle and the indexed
     * unique one, so the common call costs a single indexed lookup.
     */
    public function findByReference(string $reference): ?PatientPayment
    {
        return PatientPayment::where('reference', $reference)->first()
            ?: PatientPayment::where('access_code', $reference)->first();
    }

    /**
     * Everything that follows a confirmed charge, in one transaction.
     *
     * The bill, its lines, the payment row and — when the charge came through a
     * shared link — the support request all move together. A crash midway would
     * otherwise leave a bill credited with a payment nothing points at.
     *
     * @param  array<string, mixed>  $data  Paystack's verification payload
     * @return array<string, mixed>
     */
    protected function settle(Tenant $tenant, PatientPayment $payment, array $data): array
    {
        $paidAmount = $this->paystack->toNaira($data['amount'] ?? 0);

        DB::connection('tenant')->transaction(function () use ($payment, $data, $paidAmount) {
            $split = $data['split'] ?? [];

            $payment->forceFill([
                'status' => PatientPayment::SUCCESS,
                'amount' => $paidAmount,
                'channel' => $data['channel'] ?? $payment->channel,
                'paid_at' => !empty($data['paid_at']) ? date('Y-m-d H:i:s', strtotime($data['paid_at'])) : now(),
                'verified_at' => now(),
                'fees' => isset($data['fees']) ? $this->paystack->toNaira($data['fees']) : null,
                'hospital_amount' => isset($split['subaccounts'][0]['amount'])
                    ? $this->paystack->toNaira($split['subaccounts'][0]['amount'])
                    : null,
                'platform_amount' => isset($data['fees_split']['integration'])
                    ? $this->paystack->toNaira($data['fees_split']['integration'])
                    : null,
                'failure_reason' => null,
                'gateway_response' => $data,
            ])->save();

            $bill = BillingLog::find($payment->billing_id);

            if ($bill) {
                $this->applyToBill($bill, $paidAmount, $payment->channel);
            }

            if ($payment->support_request_id) {
                $this->applyToSupportRequest($payment);
            }
        });

        $payment = $payment->fresh();

        $this->announce($tenant, $payment);

        return $this->result($payment);
    }

    /**
     * Move a confirmed amount onto the invoice and its lines.
     *
     * The lines are settled oldest first until the money runs out, which is what
     * makes a part payment mean something specific — the earliest unpaid item is
     * the one that clears. The invoice totals are then recomputed from the lines
     * rather than incremented, so a bill can never drift out of step with what
     * it is made of.
     */
    protected function applyToBill(BillingLog $bill, float $amount, ?string $channel): void
    {
        $remaining = $amount;

        foreach ($bill->billingLogDetails()->orderBy('id')->get() as $detail) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = max(0, round((float) $detail->amount - (float) $detail->amount_paid, 2));

            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            $paid = round((float) $detail->amount_paid + $applied, 2);

            $detail->forceFill([
                'amount_paid' => $paid,
                'status' => $paid >= (float) $detail->amount ? 'Paid' : 'Part Paid',
            ])->save();

            $remaining = round($remaining - $applied, 2);
        }

        $grandTotal = $this->billing->grandTotalFor($bill);
        $totalPaid = round((float) $bill->amount_paid + $amount, 2);

        // Capped so a rounding difference between the lines and the invoice can
        // never report more paid than the invoice is worth. Skipped when the
        // invoice has no total at all, where capping would zero the payment
        // instead of bounding it.
        if ($grandTotal > 0) {
            $totalPaid = min($totalPaid, $grandTotal);
        }

        $outstanding = max(0, round($grandTotal - $totalPaid, 2));

        $bill->forceFill([
            'amount_paid' => $totalPaid,
            'grand_total' => $grandTotal,
            'amount_outstanding' => $outstanding,
            'payment_method' => $this->paymentMethodLabel($channel),
            'payment_status' => match (true) {
                $outstanding <= 0 => 'Paid',
                $totalPaid > 0 => 'Part Paid',
                default => 'Pending',
            },
        ])->save();
    }

    /**
     * Credit a shared link with a contribution, and close it once it is covered.
     */
    protected function applyToSupportRequest(PatientPayment $payment): void
    {
        $request = PaymentSupportRequest::find($payment->support_request_id);

        if (!$request) {
            return;
        }

        // Summed from the contributions rather than incremented, so the total
        // stays right even if a verification is somehow replayed.
        $raised = round((float) $request->payments()->successful()->sum('amount'), 2);

        $request->forceFill([
            'raised_amount' => $raised,
            'status' => $raised >= (float) $request->target_amount
                ? PaymentSupportRequest::COMPLETED
                : $request->status,
            'completed_at' => $raised >= (float) $request->target_amount
                ? ($request->completed_at ?: now())
                : $request->completed_at,
        ])->save();
    }

    /**
     * Tell the patient their bill moved.
     *
     * A contribution reads differently from a payment the patient made
     * themselves — one is news about somebody else's kindness, the other is a
     * receipt — so the two are worded and typed apart.
     */
    protected function announce(Tenant $tenant, PatientPayment $payment): void
    {
        $patient = $payment->patient;

        if (!$patient) {
            return;
        }

        $amount = number_format((float) $payment->amount, 2);
        $data = [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'billing_id' => $payment->billing_id,
        ];

        if ($payment->is_support) {
            $request = PaymentSupportRequest::find($payment->support_request_id);
            $isComplete = $request && $request->status === PaymentSupportRequest::COMPLETED;

            $this->notifications->notify(
                $tenant,
                $patient,
                $isComplete ? 'payment_support_completed' : 'payment_support_received',
                $isComplete ? 'Payment support completed' : 'Someone supported your bill',
                $isComplete
                    ? 'Your sponsor has successfully paid your outstanding bill.'
                    : $payment->supporter_name . ' contributed ' . $amount . ' towards your bill.',
                $data + ['support_request_id' => $payment->support_request_id]
            );

            return;
        }

        $this->notifications->notify(
            $tenant,
            $patient,
            'payment_successful',
            'Payment successful',
            'We have received your payment of ' . $amount . '.',
            $data
        );
    }

    /**
     * Write the payment row and get the checkout page for it.
     *
     * Shared by the patient's own payment and by a friend's contribution: it is
     * the same charge, to the same subaccount, against the same bill. Only who
     * is paying differs, which is what `attributes` carries.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>|null  $channels
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function startCheckout(
        Tenant $tenant,
        BillingLog $bill,
        float $amount,
        string $email,
        ?string $payerName = null,
        array $attributes = [],
        ?array $channels = null,
        ?string $callbackUrl = null
    ): array {
        $subaccount = $this->payoutAccounts->subaccountCodeFor($tenant);

        if (empty($subaccount)) {
            // Refused rather than charged into the platform's own balance. A
            // payment with nowhere to settle would have to be moved by hand.
            throw new PatientAppException(
                'This hospital has not finished setting up online payments yet. Please pay at the hospital.',
                409
            );
        }

        $reference = $this->paystack->generateReference('EMED');

        $payment = PatientPayment::create([
            'tenant_id' => $tenant->uuid,
            'billing_id' => $bill->id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => config('services.paystack.currency', 'NGN'),
            'status' => PatientPayment::PENDING,
            'payer_name' => $payerName,
            'payer_email' => $email,
            'subaccount_code' => $subaccount,
        ] + $attributes);

        try {
            $response = $this->paystack->initializeTransaction([
                'email' => $email,
                'amount' => $amount,
                'reference' => $reference,
                'subaccount' => $subaccount,
                'channels' => $channels ?: config('services.paystack.channels'),

                // A contribution sends the payer back to the page they came
                // from rather than to the app's own callback, because a friend
                // paying through a shared link has no app to return to.
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'billing_id' => $bill->id,
                    'invoice_number' => $bill->invoice_number,
                    'tenant' => $tenant->uuid,
                    'hospital' => $tenant->name,
                    'support_request_id' => $attributes['support_request_id'] ?? null,
                ],
            ]);
        } catch (PaystackException $th) {
            $payment->forceFill([
                'status' => PatientPayment::FAILED,
                'failure_reason' => $th->getMessage(),
            ])->save();

            Log::warning('Could not start a patient checkout.', [
                'tenant_id' => $tenant->id,
                'billing_id' => $bill->id,
                'reference' => $reference,
                'message' => $th->getMessage(),
            ]);

            throw new PatientAppException(
                'We could not start that payment. Please try again in a moment.',
                $th->getCode() === 503 ? 503 : 502
            );
        }

        $data = $response['data'] ?? [];

        $payment->forceFill([
            'access_code' => $data['access_code'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
        ])->save();

        return [
            'reference' => $reference,
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'public_key' => config('services.paystack.public_key'),
            'amount' => $amount,
            'currency' => config('services.paystack.currency', 'NGN'),
            'channels' => $channels ?: config('services.paystack.channels'),
            'payment' => $payment->fresh(),
        ];
    }

    /**
     * What a verification answers with.
     *
     * @return array<string, mixed>
     */
    protected function result(PatientPayment $payment, bool $alreadySettled = false): array
    {
        $bill = BillingLog::with('billingLogDetails')->find($payment->billing_id);

        return [
            'payment' => $payment,
            'settled' => $payment->status === PatientPayment::SUCCESS,
            'already_settled' => $alreadySettled,
            'bill' => $bill ? [
                'id' => $bill->id,
                'invoice_number' => $bill->invoice_number,
                'payment_status' => $bill->payment_status,
                'amount_paid' => round((float) $bill->amount_paid, 2),
                'outstanding' => $this->billing->outstandingFor($bill),
            ] : null,
        ];
    }

    /**
     * How the channel is written on the invoice, in the wording the hospital
     * console already uses for the same thing.
     */
    protected function paymentMethodLabel(?string $channel): string
    {
        return match ($channel) {
            'card' => 'Card',
            'bank_transfer', 'bank' => 'Bank Transfer',
            'ussd' => 'USSD',
            default => 'Online Payment',
        };
    }
}
