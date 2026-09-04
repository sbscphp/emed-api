<?php

namespace App\Services\Patient\Billing;

use App\Exceptions\PatientAppException;
use App\Models\BillingLog;
use App\Models\Patient;
use App\Models\PaymentSupportRequest;
use App\Models\Tenant;
use App\Services\Patient\PatientContextService;
use Illuminate\Support\Str;

/**
 * Class PaymentSupportService
 *
 * A patient asking people they trust to help pay one bill, and those people
 * paying it.
 *
 * Two audiences, one object. The patient generates a link and watches what comes
 * in; anyone holding the link opens a public page, sees what is being asked for
 * and contributes. The page is public by necessity — a friend has no account
 * here — which is what shapes everything below:
 *
 *   - the link carries a random token, never the bill id, so one link cannot be
 *     edited into somebody else's bill;
 *   - it expires, so a link left in a group chat stops working;
 *   - what the public side is shown is built by publicView(), and that method is
 *     the whole of what a stranger can learn. A first name, the service the bill
 *     is for, the hospital, and the amounts. No record, no contact details, no
 *     invoice lines, no other bill.
 *
 * The money itself goes the same way as any other payment: the same charge to
 * the same hospital subaccount against the same bill, through
 * PatientPaymentService, so a bill does not care who paid it.
 */
class PaymentSupportService
{
    public function __construct(
        protected PatientContextService $context,
        protected PatientBillingService $billing,
        protected PatientPaymentService $payments,
    ) {}

    /**
     * Create — or hand back — the link for one bill.
     *
     * A bill has at most one live request. Asking again returns the existing one
     * rather than minting a second link, because two links against one bill
     * would split the progress a patient is watching.
     *
     * @param  int  $billId
     * @param  array<string, mixed>  $data
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function create($billId, array $data = []): PaymentSupportRequest
    {
        $tenant = $this->context->tenant();
        $patient = $this->context->patient();

        $bill = $this->billing->findOwnedBill($billId);
        $outstanding = $this->billing->outstandingFor($bill);

        if ($outstanding <= 0) {
            throw new PatientAppException('This bill has already been paid.', 409);
        }

        $existing = $this->activeRequestForBill($bill->id);

        if ($existing) {
            return $existing;
        }

        return PaymentSupportRequest::create([
            'tenant_id' => $tenant->uuid,
            'billing_id' => $bill->id,
            'patient_id' => $patient->id,
            'user_id' => $this->context->user()->id,
            'token' => $this->generateToken(),
            'target_amount' => $outstanding,
            'raised_amount' => 0,
            'message' => $data['message'] ?? null,
            'status' => PaymentSupportRequest::ACTIVE,
            'expires_at' => now()->addDays(config('services.paystack.support_link_days', 7)),
        ]);
    }

    /**
     * The patient's own view of a request: the progress bar and who has given.
     *
     * @param  int  $id
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): PaymentSupportRequest
    {
        $request = PaymentSupportRequest::query()
            ->where('patient_id', $this->context->patient()->id)
            ->with(['contributions', 'billing.billingLogDetails.serviceUnit'])
            ->find($id);

        if (!$request) {
            throw new PatientAppException('We could not find that support request.', 404);
        }

        return $this->closeIfExpired($request);
    }

    /**
     * Every request this patient has raised at this hospital, newest first.
     *
     * Paginated by the query builder rather than in PHP: unlike the bill lists,
     * nothing here is derived, so the database can do the counting and only a
     * page of rows is ever loaded with its contributions.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index($request = null)
    {
        $query = PaymentSupportRequest::query()
            ->where('patient_id', $this->context->patient()->id)
            ->when(!empty($request['status']), fn($q) => $q->where('status', $request['status']))
            ->with(['contributions', 'billing.billingLogDetails.serviceUnit'])
            ->orderBy('id', 'DESC');

        if (!empty($request['paginate'])) {
            $page = $query->paginate($request['limit'] ?? 15);

            // Expiry is settled on read, so it is applied to the rows on this
            // page the same way it is to a single request.
            $page->getCollection()->transform(fn($record) => $this->closeIfExpired($record));

            return $page;
        }

        return $query
            ->when(!empty($request['limit']), fn($q) => $q->limit((int) $request['limit']))
            ->get()
            ->map(fn($record) => $this->closeIfExpired($record));
    }

    /**
     * Stop taking contributions on a link the patient no longer wants live.
     *
     * @param  int  $id
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function cancel($id): PaymentSupportRequest
    {
        $request = $this->show($id);

        if ($request->status === PaymentSupportRequest::COMPLETED) {
            throw new PatientAppException('This bill has already been covered.', 409);
        }

        $request->forceFill(['status' => PaymentSupportRequest::CANCELLED])->save();

        return $request->fresh(['contributions', 'billing.billingLogDetails.serviceUnit']);
    }

    /**
     * Resolve a shared link, for the public page.
     *
     * Returns the tenant alongside the request because the caller has no tenant
     * header to work from — the token is the only thing a friend arrives with,
     * and the hospital has to be found from it before anything else can be read.
     *
     * @return array{tenant: \App\Models\Tenant, request: \App\Models\PaymentSupportRequest}
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function resolveToken(string $token): array
    {
        $tenant = $this->tenantHoldingToken($token);

        $request = PaymentSupportRequest::where('token', $token)
            ->with(['contributions', 'patient', 'billing.billingLogDetails.serviceUnit'])
            ->first();

        if (!$request) {
            throw new PatientAppException('This support link is not valid.', 404);
        }

        return ['tenant' => $tenant, 'request' => $this->closeIfExpired($request)];
    }

    /**
     * The whole of what somebody holding a link is allowed to see.
     *
     * Everything a supporter is shown is assembled here, by hand, field by
     * field. Nothing about the patient's record can reach the page by accident,
     * because nothing reaches it except what is written below.
     *
     * @return array<string, mixed>
     */
    public function publicView(Tenant $tenant, PaymentSupportRequest $request): array
    {
        $patient = $request->patient;

        return [
            'token' => $request->token,

            // A first name only. Enough for a friend to recognise who they are
            // helping, and not enough to identify a patient to a stranger.
            'patient_first_name' => $this->firstName($patient),

            'hospital' => [
                'name' => $tenant->name,
                'logo' => $tenant->logo,
            ],

            // What the money is for, in the same words the patient's own bill
            // screen uses — a service name, never a diagnosis or a test result.
            'service' => $request->service_title,

            'currency' => config('services.paystack.currency', 'NGN'),
            'target_amount' => round((float) $request->target_amount, 2),
            'raised_amount' => round((float) $request->raised_amount, 2),
            'outstanding_amount' => $request->outstanding_amount,
            'progress_percent' => $request->progress_percent,
            'supporters_count' => $request->contributions->count(),
            'message' => $request->message,

            'is_open' => $request->is_open,
            'status' => $request->status,
            'expires_at' => optional($request->expires_at)->toDateTimeString(),

            // Suggested amounts, so the page can offer the chips the design
            // shows without inventing figures that overshoot what is owed.
            'suggested_amounts' => $this->suggestedAmounts($request->outstanding_amount),

            'supporters' => $request->contributions->map(fn($payment) => [
                'name' => $payment->supporter_name,
                'amount' => round((float) $payment->amount, 2),
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
            ])->values(),
        ];
    }

    /**
     * A friend starting a contribution.
     *
     * Runs without any authentication, so every guard the patient's own payment
     * gets from its token is applied here explicitly: the link has to be open,
     * the bill has to still be owed, and the amount cannot exceed what is left.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function contribute(string $token, array $data): array
    {
        ['tenant' => $tenant, 'request' => $request] = $this->resolveToken($token);

        if (!$request->is_open) {
            throw new PatientAppException(
                $request->status === PaymentSupportRequest::COMPLETED
                    ? 'This bill has already been fully covered. Thank you.'
                    : 'This support link is no longer active.',
                409
            );
        }

        $bill = BillingLog::find($request->billing_id);

        if (!$bill) {
            throw new PatientAppException('This support link is not valid.', 404);
        }

        $outstanding = min($request->outstanding_amount, $this->billing->outstandingFor($bill));

        if ($outstanding <= 0) {
            $request->forceFill([
                'status' => PaymentSupportRequest::COMPLETED,
                'completed_at' => $request->completed_at ?: now(),
            ])->save();

            throw new PatientAppException('This bill has already been paid. Thank you.', 409);
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new PatientAppException('Enter an amount to give.', 422);
        }

        if ($amount > $outstanding) {
            throw new PatientAppException(
                'Only ' . number_format($outstanding, 2) . ' is still needed for this bill.',
                422
            );
        }

        return $this->payments->startCheckout(
            tenant: $tenant,
            bill: $bill,
            amount: $amount,
            email: $data['email'],
            payerName: $data['name'] ?? null,
            attributes: [
                // Stamped with the patient so the bill, the notification and the
                // patient's own progress screen all find the contribution, even
                // though the payer has no account of their own.
                'patient_id' => $request->patient_id,
                'support_request_id' => $request->id,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            ],
            callbackUrl: $this->callbackUrl($request),
        );
    }

    /**
     * Confirm a contribution.
     *
     * The public counterpart of the patient's verify, resolving the hospital
     * from the token rather than from a header.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function verifyContribution(string $token, string $reference): array
    {
        ['tenant' => $tenant, 'request' => $request] = $this->resolveToken($token);

        // Either handle the payer could be holding — the reference or the
        // access code the checkout URL ended in.
        $payment = $this->payments->findByReference($reference);

        // Checked before verifying: a payment from another bill must not be
        // confirmable through somebody else's link.
        if (!$payment || (int) $payment->support_request_id !== (int) $request->id) {
            throw new PatientAppException('We could not find that payment.', 404);
        }

        $result = $this->payments->verify($payment->reference, $tenant);

        $result['support'] = $this->publicView($tenant, $request->fresh()->load('contributions', 'patient', 'billing'));

        return $result;
    }

    /**
     * The link the patient shares.
     *
     * Kept as a named method for callers that read better this way; the value
     * itself is the model's, so there is one definition of what the link is.
     */
    public function shareUrl(PaymentSupportRequest $request): string
    {
        return $request->share_url;
    }

    /**
     * Where Paystack returns a supporter once checkout closes.
     *
     * The page they came from, carrying the token, so the return lands on the
     * right request without the browser having had to remember anything. The
     * reference Paystack appends is what the page then verifies.
     */
    public function callbackUrl(PaymentSupportRequest $request): string
    {
        return $request->share_url . '/callback';
    }

    /**
     * The live request for a bill, if there is one.
     */
    protected function activeRequestForBill(int $billId): ?PaymentSupportRequest
    {
        $request = PaymentSupportRequest::where('billing_id', $billId)
            ->where('status', PaymentSupportRequest::ACTIVE)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$request) {
            return null;
        }

        $request = $this->closeIfExpired($request);

        return $request->is_open ? $request : null;
    }

    /**
     * Close a link that has run past its date.
     *
     * Done on read rather than by a scheduled job, so an expired link is
     * expired the moment somebody opens it whether or not a worker has run.
     */
    protected function closeIfExpired(PaymentSupportRequest $request): PaymentSupportRequest
    {
        if (
            $request->status === PaymentSupportRequest::ACTIVE
            && $request->expires_at
            && $request->expires_at->isPast()
        ) {
            $request->forceFill(['status' => PaymentSupportRequest::EXPIRED])->save();
        }

        return $request;
    }

    /**
     * A token nothing can be guessed from.
     *
     * Regenerated on the vanishingly unlikely collision rather than trusted, as
     * a duplicate would hand one patient's link to another's bill.
     */
    protected function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (PaymentSupportRequest::where('token', $token)->exists());

        return $token;
    }

    /**
     * Find the hospital a shared token belongs to.
     *
     * Support requests live in each hospital's own database, and a friend
     * arrives with nothing but the token, so the databases are asked in turn
     * until one recognises it. Bounded by the number of hospitals on the
     * platform and only ever run for the public pages.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function tenantHoldingToken(string $token): Tenant
    {
        foreach (Tenant::query()->where('status', 'Active')->cursor() as $tenant) {
            $tenant->makeCurrent();

            try {
                $exists = PaymentSupportRequest::where('token', $token)->exists();
            } catch (\Throwable $th) {
                // A hospital whose database has not been migrated yet simply
                // does not hold this token.
                continue;
            }

            if ($exists) {
                return $tenant;
            }
        }

        throw new PatientAppException('This support link is not valid.', 404);
    }

    /**
     * The patient's first name, and nothing more of it.
     */
    protected function firstName(?Patient $patient): string
    {
        if (!$patient) {
            return 'A patient';
        }

        return trim((string) $patient->firstname) ?: 'A patient';
    }

    /**
     * The quick-pick amounts on the public page.
     *
     * Capped at what is still needed, so no chip ever offers more than the bill
     * can accept and gets rejected at checkout.
     *
     * @return array<int, float>
     */
    protected function suggestedAmounts(float $outstanding): array
    {
        return collect([5000, 10000, 15000, 25000])
            ->filter(fn($amount) => $amount <= $outstanding)
            ->push($outstanding)
            ->unique()
            ->sort()
            ->values()
            ->map(fn($amount) => round((float) $amount, 2))
            ->all();
    }
}
