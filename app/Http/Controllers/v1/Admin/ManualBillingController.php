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
                'billing'     => $billing->fresh(['patient', 'billingLogDetails', 'transactions']),
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while recording the payment.', [], 500, $th);
        }
    }
}
