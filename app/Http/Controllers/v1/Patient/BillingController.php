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
 * what is still owed; they pay a bill, which is a checkout started in one call
 * and confirmed in another; and they raise a link asking people they trust to
 * help pay one, then watch what comes in.
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
     * The Billing screen: the summary card, the outstanding list and the paid
     * list, filtered by `tab`, `search_param` or a date.
     *
     * Both lists page independently — `outstanding_page` and `paid_page` — so
     * "View all" on one of them can walk its pages while the other stays put.
     * Send `paginate=1` to get pages, `limit` on its own for the first few rows
     * of each, and neither for the lot.
     */
    public function index(BillingIndexRequest $request)
    {
        try {
            $records = $this->billingService->index($request);

            return JsonResponser::send(false, 'Bills retrieved successfully.', [
                'summary' => $records['summary'],
                'filter' => $records['filter'],
                'outstanding' => $this->list($records['outstanding'], $request->paginate),
                'paid' => $this->list($records['paid'], $request->paginate),
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
     * POST /v1/patient/billing/{id}/pay
     *
     * Start a checkout. Answers with the Paystack page to open and the reference
     * to verify afterwards. Nothing has been paid at this point.
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
                    'bill' => $result['bill'],
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
     */
    public function supportRequests(SupportIndexRequest $request)
    {
        try {
            $records = $this->supportService->index($request);

            return JsonResponser::send(
                false,
                'Support requests retrieved successfully.',
                $this->list($records, $request->paginate, SupportRequestResource::class),
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
