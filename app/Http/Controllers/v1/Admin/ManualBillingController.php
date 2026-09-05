<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Enums\PaymentChannelEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualBillingRequest;
use App\Models\BillingLog;
use App\Responser\JsonResponser;
use App\Services\Payment\PaymentReconciliationService;
use App\Services\Revamp\ManualBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ManualBillingController extends Controller
{
    public function __construct(
        private ManualBillingService $manualBillingService,
        private PaymentReconciliationService $reconciliation,
        private \App\Services\Billing\InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        try {
            $records = $this->manualBillingService->index($request);

            return JsonResponser::send(false, 'Billing record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching billings.', [], 500, $th);
        }
    }

    public function store(ManualBillingRequest $request)
    {
        try {
            $billing = $this->manualBillingService->create($request->validated());

            return JsonResponser::send(false, 'Billing created successfully', $billing, 201);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while creating the billing.', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            $billing = $this->manualBillingService->find($id);

            return JsonResponser::send(false, 'Billing found successfully', $billing);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Billing not found.', [], 404, $th);
        }
    }

    public function update(ManualBillingRequest $request, $id)
    {
        try {
            $billing = $this->manualBillingService->update($id, $request->validated());

            return JsonResponser::send(false, 'Billing updated successfully', $billing);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 500, $th);
        }
    }

    /**
     * Record a bank transfer / cash / POS payment against a billing. This is the
     * billing manager's manual reconciliation path (the card path is automatic).
     */
    public function recordPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'billing_id' => ['required', 'exists:tenant.billing_logs,id'],
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'channel'    => ['required', 'in:transfer,cash,pos,insurance'],
            'reference'  => ['nullable', 'string', 'max:255'],
            'note'       => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $billing = BillingLog::findOrFail($request->billing_id);

            if ($billing->payment_status === GeneralEnums::PAID->value) {
                return JsonResponser::send(true, 'This billing is already fully paid.', [], 422);
            }

            $outstanding = round((float) $billing->amount_outstanding, 2);
            if (round((float) $request->amount, 2) > $outstanding) {
                return JsonResponser::send(true, "Amount exceeds the outstanding balance of {$outstanding}.", [], 422);
            }

            $transaction = $this->reconciliation->recordManualPayment(
                $billing,
                (float) $request->amount,
                $request->channel ?? PaymentChannelEnum::TRANSFER->value,
                [
                    'reference'  => $request->reference,
                    'created_by' => Auth::id(),
                    'metadata'   => ['note' => $request->note],
                ],
            );

            return JsonResponser::send(false, 'Payment recorded successfully', [
                'transaction' => $transaction,
                'billing'     => $billing->fresh(['patient', 'billingLogDetails.serviceUnit', 'transactions']),
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while recording the payment.', [], 500, $th);
        }
    }

    /**
     * Download the invoice for a manual billing as a PDF.
     */
    public function downloadInvoice($id)
    {
        try {
            $billing = $this->manualBillingService->find($id);

            return $this->invoiceService->download($billing);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Invoice not found.', [], 404, $th);
        }
    }

    /**
     * (Re)send the invoice PDF to the patient's email, or to an override address.
     */
    public function sendInvoice(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['nullable', 'email'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $billing = $this->manualBillingService->find($id);
            $sent = $this->invoiceService->sendToPatient($billing, $request->email);

            if (!$sent) {
                return JsonResponser::send(true, 'No email address available for this patient. Provide an email to send to.', [], 422);
            }

            return JsonResponser::send(false, 'Invoice sent successfully', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while sending the invoice.', [], 500, $th);
        }
    }
}
