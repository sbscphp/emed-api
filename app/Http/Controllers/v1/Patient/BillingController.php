<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Billing\BillingIndexRequest;
use App\Http\Requests\Patient\Billing\CreateSupportRequestRequest;
use App\Http\Requests\Patient\Billing\InitiatePaymentRequest;
use App\Http\Requests\Patient\Billing\SupportIndexRequest;
use App\Http\Resources\Patient\BillDetailResource;
use App\Http\Resources\Patient\BillResource;
use App\Http\Resources\Patient\PaymentResource;
use App\Http\Resources\Patient\SupportRequestResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Billing\PatientBillingService;
use App\Services\Patient\Billing\PatientPaymentService;
use App\Services\Patient\Billing\PaymentSupportService;
use Throwable;

/**
 * The billing module of the patient mobile app.
 *
 * Three things happen here. The patient reads what they have been billed and
 * what is still owed, and downloads a receipt for anything settled; they pay a
 * bill, which is a checkout started in one call and confirmed in another; and
 * they raise a link asking people they trust to help pay one, then watch what
 * comes in.
 *
 * Paying is never one call. `pay` writes the payment and hands back a Paystack
 * page; `verify` is what actually settles the bill, and only on Paystack's word.
 * There is no webhook, so `verify` is also the recovery path for a payment
 * nobody came back from — it is safe to call as many times as the app likes.
 */
class BillingController extends Controller
{
    public function __construct(
        protected PatientBillingService $billingService,
        protected PatientPaymentService $paymentService,
        protected PaymentSupportService $supportService,
    ) {}

    /**
     * GET /v1/patient/billing
     *
     * The Billing screen: the summary card and one list of bills, narrowed by
     * `tab` — outstanding, paid, or all — and by `search_param` or a date.
     *
     * One list means one page cursor, so `page` walks it as it does everywhere
     * else in the app. Send `paginate=1` for pages, `limit` on its own for the
     * first few rows, and neither for the lot. The summary still counts both
     * sides, so the tab that is not being listed can still be labelled.
     */
    public function index(BillingIndexRequest $request)
    {
        try {
            $records = $this->billingService->index($request);

            return JsonResponser::send(false, 'Bills retrieved successfully.', [
                'summary' => $records['summary'],
                'filter' => $records['filter'],
                'records' => $this->list($records['bills'], $request->paginate),
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * A list, as a bare array of rows or as a page with its links and totals
     * around it.
     *
     * The shape is the caller's choice rather than the endpoint's, so a screen
     * showing a preview and a screen walking the full history can use the same
     * route.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $resourceClass
     * @return array<string, mixed>|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    protected function list($records, $paginate, string $resourceClass = BillResource::class)
    {
        $resource = $resourceClass::collection($records);

        return $paginate ? $resource->response()->getData(true) : $resource;
    }

    /**
     * GET /v1/patient/billing/{id}
     *
     * One invoice — the Bill Details screen.
     */
    public function show($id)
    {
        try {
            $bill = $this->billingService->show($id);

            return JsonResponser::send(
                false,
                'Bill retrieved successfully.',
                new BillDetailResource($bill),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/billing/{id}/receipt
     *
     * "Download Receipt" on a paid invoice. Streams the PDF rather than
     * answering in the JSON envelope, so the app can hand it straight to the
     * file system or a share sheet. A bill still carrying a balance answers 409
     * rather than an empty document.
     */
    public function receipt($id)
    {
        try {
            return $this->billingService->receipt($id);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/billing/{id}/pay
     *
     * Start a checkout. Answers with the Paystack page to open and the reference
     * to verify afterwards. Nothing has been paid at this point.
     *
     * `callback_url` is the page Paystack returns the payer to when checkout
     * closes — the same page whether it succeeded or not, so it is what the app
     * watches for to know the sheet is done, not what tells it the outcome.
     * Only `verify` settles anything.
     */
    public function pay(InitiatePaymentRequest $request, $id)
    {
        try {
            $checkout = $this->paymentService->initiate($id, $request->validated());

            return JsonResponser::send(false, 'Payment initiated successfully.', [
                'reference' => $checkout['reference'],
                'authorization_url' => $checkout['authorization_url'],
                'access_code' => $checkout['access_code'],
                'public_key' => $checkout['public_key'],
                'amount' => $checkout['amount'],
                'currency' => $checkout['currency'],
                'channels' => $checkout['channels'],
                'callback_url' => $checkout['callback_url'],
                'payment' => new PaymentResource($checkout['payment']),
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/billing/pay-all
     *
     * "Pay now" on the Outstanding Bills card: one checkout for everything the
     * patient still owes this hospital.
     *
     * No ids are sent. The app has no business deciding which invoices are
     * outstanding, and a list travelling over the wire is a list that can arrive
     * stale — the set is read on the server from the same query the card's total
     * comes from, and travels back under `bills` so the app can show what is
     * about to be paid.
     *
     * Settled by the same `verify` as any other payment; which invoice each
     * naira lands on is worked out there, oldest bill first.
     */
    public function payAll(InitiatePaymentRequest $request)
    {
        try {
            $checkout = $this->paymentService->initiateAll($request->validated());

            return JsonResponser::send(false, 'Payment initiated successfully.', [
                'reference' => $checkout['reference'],
                'authorization_url' => $checkout['authorization_url'],
                'access_code' => $checkout['access_code'],
                'public_key' => $checkout['public_key'],
                'amount' => $checkout['amount'],
                'currency' => $checkout['currency'],
                'channels' => $checkout['channels'],
                'callback_url' => $checkout['callback_url'],
                'bills' => $checkout['bills'],
                'bills_count' => $checkout['bills_count'],
                'outstanding_total' => $checkout['outstanding_total'],
                'payment' => new PaymentResource($checkout['payment']),
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/billing/payments/{reference}/verify
     *
     * Confirm a payment and settle the bill.
     *
     * The app calls this when checkout closes, and may call it again later for
     * any reference it is unsure about — a second call finds the bill already
     * credited and answers with the same result rather than paying it twice.
     */
    public function verify($reference)
    {
        try {
            $result = $this->paymentService->verify($reference);

            return JsonResponser::send(
                false,
                $result['settled'] ? 'Payment confirmed successfully.' : 'This payment has not been completed.',
                [
                    'settled' => $result['settled'],
                    'already_settled' => $result['already_settled'],

                    // The invoice the checkout was raised against, and — for a
                    // "pay all" charge — every invoice it cleared, each with the
                    // share of the payment that landed on it.
                    'bill' => $result['bill'],
                    'bills' => $result['bills'],
                    'is_bulk' => $result['is_bulk'],
                    'payment' => new PaymentResource($result['payment']),
                ],
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/patient/billing/{id}/support
     *
     * Generate the link the patient shares to ask for help with this bill. A
     * bill that already has a live link gets that one back rather than a second.
     */
    public function createSupport(CreateSupportRequestRequest $request, $id)
    {
        try {
            $support = $this->supportService->create($id, $request->validated());

            return JsonResponser::send(
                false,
                'Payment link generated successfully.',
                new SupportRequestResource($this->supportService->show($support->id)),
                201
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/billing/support
     *
     * Every support request this patient has raised at this hospital, newest
     * first. Send `paginate=1` for pages, `limit` on its own for the first few,
     * and `status` to narrow to one of Active / Completed / Expired / Cancelled.
     *
     * The list sits under `records`, as it does on the Billing screen.
     */
    public function supportRequests(SupportIndexRequest $request)
    {
        try {
            $records = $this->supportService->index($request);

            return JsonResponser::send(
                false,
                'Support requests retrieved successfully.',
                [
                    'records' => $this->list($records, $request->paginate, SupportRequestResource::class),
                ],
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/billing/support/{id}
     *
     * The progress screen: how much has been raised, and who gave it.
     */
    public function showSupport($id)
    {
        try {
            $support = $this->supportService->show($id);

            return JsonResponser::send(
                false,
                'Support request retrieved successfully.',
                new SupportRequestResource($support),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/billing/support/{id}/cancel
     *
     * Stop taking contributions on a link the patient no longer wants live.
     */
    public function cancelSupport($id)
    {
        try {
            $support = $this->supportService->cancel($id);

            return JsonResponser::send(
                false,
                'Payment link cancelled successfully.',
                new SupportRequestResource($support),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
