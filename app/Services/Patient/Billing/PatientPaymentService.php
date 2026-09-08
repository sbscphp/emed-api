<?php

namespace App\Services\Patient\Billing;

use App\Exceptions\PatientAppException;
use App\Mail\PatientPaymentReceiptMail;
use App\Models\BillingLog;
use App\Models\PatientPayment;
use App\Models\PaymentSupportRequest;
use App\Models\Tenant;
use App\Services\Billing\HospitalPayoutAccountService;
use App\Services\Patient\Notification\PatientNotificationService;
use App\Services\Patient\PatientContextService;
use App\Services\Patient\Reports\PatientReportService;
use App\Services\Payment\PaystackException;
use App\Services\Payment\PaystackService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        protected PatientReportService $reports,
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
     * Start one checkout for everything the patient still owes this hospital.
     *
     * "Pay now" on the Outstanding Bills card. The app sends no ids — it has no
     * business deciding which invoices are outstanding, and a list travelling
     * over the wire is a list that can arrive stale or belonging to somebody
     * else. The set is read here, from the same query the card's own total is
     * counted from, so the amount charged is the amount shown.
     *
     * One Paystack transaction rather than several, because a patient tapping
     * one button expects one card entry and one line on their statement. Which
     * invoice each naira lands on is settled afterwards, in verify().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function initiateAll(array $data = []): array
    {
        $tenant = $this->context->tenant();
        $patient = $this->context->patient();
        $user = $this->context->user();

        $bills = $this->billing->outstandingBills();

        if ($bills->isEmpty()) {
            throw new PatientAppException('You have no outstanding bills at this hospital.', 409);
        }

        $outstanding = round($bills->sum(fn($bill) => $this->billing->outstandingFor($bill)), 2);

        if ($outstanding <= 0) {
            throw new PatientAppException('You have no outstanding bills at this hospital.', 409);
        }

        // Part payment is allowed here for the same reason it is on a single
        // bill: a patient who can only manage some of it should still be able to
        // pay some of it. What they send clears the oldest invoices first.
        $amount = isset($data['amount']) && $data['amount'] !== null
            ? round((float) $data['amount'], 2)
            : $outstanding;

        if ($amount <= 0) {
            throw new PatientAppException('Enter an amount to pay.', 422);
        }

        if ($amount > $outstanding) {
            throw new PatientAppException(
                'That is more than you currently owe. The most you can pay is ' . number_format($outstanding, 2) . '.',
                422
            );
        }

        $email = $data['email'] ?? $user->email ?? $patient->email;

        if (empty($email)) {
            throw new PatientAppException('Your account needs an email address before you can pay online.', 422);
        }

        $checkout = $this->startCheckout(
            tenant: $tenant,
            // The oldest outstanding invoice. It is the one billing_id points
            // at, so a bulk payment is still a payment against a bill to every
            // query that predates this flow.
            bill: $bills->first(),
            amount: $amount,
            email: $email,
            payerName: trim($user->fullname ?: "{$patient->firstname} {$patient->lastname}"),
            attributes: [
                'patient_id' => $patient->id,
                'user_id' => $user->id,
            ],
            channels: $data['channels'] ?? null,
            billingIds: $bills->pluck('id')->all(),
        );

        return $checkout + [
            'bills' => $bills->map(fn($bill) => [
                'id' => $bill->id,
                'invoice_number' => $bill->invoice_number,
                'service' => $bill->service_title,
                'outstanding' => $this->billing->outstandingFor($bill),
            ])->values()->all(),
            'bills_count' => $bills->count(),
            'outstanding_total' => $outstanding,
        ];
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

            $this->applyToBills($payment, $paidAmount);

            if ($payment->support_request_id) {
                $this->applyToSupportRequest($payment);
            }
        });

        $payment = $payment->fresh();

        $this->announce($tenant, $payment);
        $this->emailReceipt($tenant, $payment);

        return $this->result($payment);
    }

    /**
     * Email the payer their receipt, with the PDF attached.
     *
     * Sent from here rather than from the controllers because this is the one
     * place a payment is ever confirmed: /pay, /pay-all and a support link all
     * end up in settle(), and the already-settled branch of verify() returns
     * long before this — so a patient who verifies the same reference five times
     * is thanked once and receipted once.
     *
     * Addressed to whoever actually paid. For the patient's own bill that is
     * their own address; for a shared link it is the friend who contributed,
     * which is who a receipt belongs to. The patient still hears about that
     * payment through announce() above.
     *
     * Nothing here may cost anyone their money. The bill is already settled and
     * committed by this point, so a mail server that is down, a patient with no
     * address on file or a template that fails to render is logged and stepped
     * over rather than thrown — the payment stands either way, and the app can
     * still download the receipt from the bill itself.
     */
    protected function emailReceipt(Tenant $tenant, PatientPayment $payment): void
    {
        try {
            $patient = $payment->patient;
            $email = $payment->payer_email ?: ($patient->email ?? null);

            if (empty($email)) {
                Log::info('A payment settled with no address to send the receipt to.', [
                    'tenant_id' => $tenant->id,
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                ]);

                return;
            }

            $bills = $this->receiptBills($payment);
            $pdf = $this->reports->paymentReceipt($payment, $tenant, $patient, $bills);
            $fileName = $this->reports->paymentReceiptFileName($payment);

            $currency = $payment->currency ?: config('services.paystack.currency', 'NGN');
            $outstanding = round($bills->sum(fn($bill) => (float) $bill['outstanding']), 2);
            $patientName = trim(($patient->firstname ?? '') . ' ' . ($patient->lastname ?? '')) ?: 'there';

            Mail::to($email)->send(new PatientPaymentReceiptMail([
                'recipientName' => $payment->is_support
                    ? ($payment->payer_name ?: 'there')
                    : $patientName,
                'patientName' => $patientName,
                'isSupport' => $payment->is_support,
                'hospitalName' => $tenant->name,
                'reference' => $payment->reference,
                'currency' => $currency,
                'amount' => number_format((float) $payment->amount, 2),
                'paidAt' => optional($payment->paid_at)->format('d M Y, h:i A') ?: now()->format('d M Y, h:i A'),
                'method' => $this->paymentMethodLabel($payment->channel),
                'billCount' => $bills->count(),
                'bills' => $bills->map(fn($bill) => [
                    'invoice_number' => $bill['invoice_number'],
                    'title' => $bill['title'],
                    'paid_now' => number_format($bill['paid_now'], 2),
                ])->all(),
                'hasOutstanding' => $outstanding > 0,
                'outstandingTotal' => number_format($outstanding, 2),
                'appName' => config('patient_app.name'),
                'supportEmail' => config('patient_app.support_email'),
            ], $pdf, $fileName));
        } catch (Throwable $th) {
            Log::error('Could not email a payment receipt.', [
                'tenant_id' => $tenant->id,
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * The invoices a receipt lists, and what this payment put on each of them.
     *
     * Read after settlement, so the balance printed against a bill is the one
     * the patient will see on the app when they go looking — not the one it
     * carried a moment before the money landed.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function receiptBills(PatientPayment $payment): Collection
    {
        return collect($payment->covered_billing_ids)
            ->map(fn($id) => BillingLog::with('billingLogDetails.serviceUnit', 'service', 'serviceUnit')->find($id))
            ->filter()
            ->map(function (BillingLog $bill) use ($payment) {
                $billed = $bill->billing_date ?: $bill->created_at;

                return [
                    'id' => $bill->id,
                    'invoice_number' => $bill->invoice_number ?: ('BILL-' . $bill->id),
                    'title' => $this->billing->serviceTitle($bill),
                    'billed_at' => $billed ? Carbon::parse($billed) : null,
                    'total' => $this->billing->grandTotalFor($bill),

                    // This bill's share of the charge, not the charge: a bulk
                    // payment is worth more than any one invoice it cleared.
                    'paid_now' => $payment->amountAppliedTo($bill->id),
                    'outstanding' => $this->billing->outstandingFor($bill),
                    'items' => $bill->billingLogDetails,
                ];
            })
            ->values();
    }

    /**
     * Put a confirmed charge onto the invoices it was raised for.
     *
     * A single bill payment is unchanged: the whole amount goes on its bill, and
     * applyToBill caps it there. A bulk charge is spread across the set it
     * covers, oldest first, each invoice taking what it is owed and no more —
     * so 85,000 against a 50,000 and a 35,000 clears both, and a part payment of
     * 60,000 clears the older one and leaves 10,000 on the newer.
     *
     * What each bill received is written back onto the payment. It cannot be
     * derived afterwards — by then the bills have moved — and without it the
     * detail screen and the receipt would print the whole charge against every
     * invoice it touched.
     */
    protected function applyToBills(PatientPayment $payment, float $amount): void
    {
        $ids = $payment->covered_billing_ids;

        if (count($ids) < 2) {
            $bill = BillingLog::find($payment->billing_id);

            if ($bill) {
                $this->applyToBill($bill, $amount, $payment->channel);
            }

            return;
        }

        $bills = BillingLog::with('billingLogDetails')->whereIn('id', $ids)->get()->keyBy('id');
        $remaining = round($amount, 2);
        $allocations = [];

        foreach ($ids as $id) {
            if ($remaining <= 0) {
                break;
            }

            $bill = $bills->get($id);

            if (!$bill) {
                continue;
            }

            // Read now rather than when the checkout was started: a bill may
            // have been settled at the hospital's counter while the payer was on
            // Paystack, and paying it twice is not something we can undo.
            $outstanding = $this->billing->outstandingFor($bill);

            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);

            $this->applyToBill($bill, $applied, $payment->channel);

            $allocations[$id] = round($applied, 2);
            $remaining = round($remaining - $applied, 2);
        }

        if ($remaining > 0) {
            // Everything the charge was raised for is already covered, so there
            // is nowhere left to put this. It is not forced onto a bill — that
            // would report an invoice as overpaid — it is left for the hospital
            // to refund or credit, and said loudly enough to be found.
            Log::warning('A patient payment settled more than its bills were owed.', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $amount,
                'unallocated' => $remaining,
                'billing_ids' => $ids,
            ]);
        }

        $payment->forceFill(['allocations' => $allocations])->save();
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
            'billing_ids' => $payment->covered_billing_ids,
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

        $bills = count($payment->covered_billing_ids);

        $this->notifications->notify(
            $tenant,
            $patient,
            'payment_successful',
            'Payment successful',
            $bills > 1
                ? 'We have received your payment of ' . $amount . ' towards ' . $bills . ' bills.'
                : 'We have received your payment of ' . $amount . '.',
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
     * @param  array<int, int>|null  $billingIds  every invoice this one charge
     *                                            covers, when it covers more
     *                                            than the one it was raised for
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
        ?string $callbackUrl = null,
        ?array $billingIds = null
    ): array {
        // A contribution sends the payer back to the page they came from, because
        // a friend paying through a shared link has no app to return to. Anyone
        // else lands on the app's own callback. Resolved here rather than left to
        // PaystackService's fallback so the caller can be told which page Paystack
        // will actually land on — the app has to watch for it to know checkout is
        // over.
        $callbackUrl = $callbackUrl ?: config('services.paystack.callback_url');

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

        // Left null for the ordinary one bill checkout, so a payment that covers
        // several invoices is the only kind that carries a list, and every
        // reader can tell the two apart without counting.
        $covers = collect($billingIds ?: [])->map(fn($id) => (int) $id)->unique()->values();

        $payment = PatientPayment::create([
            'tenant_id' => $tenant->uuid,
            'billing_id' => $bill->id,
            'billing_ids' => $covers->count() > 1 ? $covers->all() : null,
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
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'billing_id' => $bill->id,
                    'billing_ids' => $covers->count() > 1 ? $covers->all() : null,
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
            'callback_url' => $callbackUrl,
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
        $ids = $payment->covered_billing_ids;
        $bills = BillingLog::with('billingLogDetails')->whereIn('id', $ids)->get()->keyBy('id');

        $settledBills = collect($ids)
            ->map(fn($id) => $bills->get($id))
            ->filter()
            ->map(fn($bill) => [
                'id' => $bill->id,
                'invoice_number' => $bill->invoice_number,
                'payment_status' => $bill->payment_status,
                'amount_paid' => round((float) $bill->amount_paid, 2),
                'amount_applied' => $payment->amountAppliedTo($bill->id),
                'outstanding' => $this->billing->outstandingFor($bill),
            ])
            ->values();

        return [
            'payment' => $payment,
            'settled' => $payment->status === PatientPayment::SUCCESS,
            'already_settled' => $alreadySettled,

            // `bill` is the invoice the checkout was raised against, kept as it
            // was so the single bill flow the app already reads is untouched.
            // `bills` is the whole set, which is the only complete answer once a
            // charge can cover more than one.
            'bill' => $settledBills->first(),
            'bills' => $settledBills->all(),
            'is_bulk' => (bool) $payment->is_bulk,
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
