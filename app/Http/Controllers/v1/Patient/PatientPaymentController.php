<?php

namespace App\Http\Controllers\v1\Patient;

use App\Enums\GeneralEnums;
use App\Http\Controllers\Controller;
use App\Models\BillingLog;
use App\Models\PaymentTransaction;
use App\Responser\JsonResponser;
use App\Services\Payment\PaymentReconciliationService;
use App\Services\Payment\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientPaymentController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private PaymentReconciliationService $reconciliation,
    ) {}

    /**
     * Start a Paystack card payment for one of the patient's own invoices.
     * Returns the authorization_url the mobile app opens, plus the reference the
     * webhook / verify-on-return use to reconcile.
     */
    public function initializeCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'billing_id'   => ['required', 'exists:tenant.billing_logs,id'],
            'amount'       => ['nullable', 'numeric', 'min:0.01'],
            'callback_url' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $patient = auth('patient')->user();

            $billing = BillingLog::where('id', $request->billing_id)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$billing) {
                return JsonResponser::send(true, 'Invoice not found.', [], 404);
            }

            if ($billing->payment_status === GeneralEnums::PAID->value || (float) $billing->amount_outstanding <= 0) {
                return JsonResponser::send(true, 'This invoice is already fully paid.', [], 422);
            }

            if (empty($patient->email)) {
                return JsonResponser::send(true, 'A valid email is required on your profile to pay by card.', [], 422);
            }

            $outstanding = (float) $billing->amount_outstanding;
            $amount = $request->filled('amount') ? min((float) $request->amount, $outstanding) : $outstanding;

            $tenantUuid = app()->bound('currentTenant') ? app('currentTenant')->uuid : null;

            $transaction = $this->reconciliation->initializeGatewayTransaction($billing, $amount, [
                'tenant'     => $tenantUuid,
                'billing_id' => $billing->id,
                'patient_id' => $patient->id,
            ], $patient->id);

            $data = $this->paystack->initialize(
                $patient->email,
                $amount,
                $transaction->reference,
                $request->callback_url,
                [
                    'tenant'     => $tenantUuid,
                    'billing_id' => $billing->id,
                    'reference'  => $transaction->reference,
                ],
            );

            return JsonResponser::send(false, 'Payment initialized', [
                'authorization_url' => $data['authorization_url'] ?? null,
                'access_code'       => $data['access_code'] ?? null,
                'reference'         => $transaction->reference,
                'amount'            => $amount,
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 500, $th);
        }
    }

    /**
     * Verify-on-return: called by the app after the Paystack sheet closes. Confirms
     * with Paystack and reconciles. Idempotent — safe alongside the webhook.
     */
    public function verifyCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, $validator->errors()->first(), $validator->errors()->all(), 400);
        }

        try {
            $patientId = auth('patient')->id();

            // Ensure the reference belongs to this patient before touching the gateway.
            $transaction = PaymentTransaction::where('reference', $request->reference)
                ->where('patient_id', $patientId)
                ->first();

            if (!$transaction) {
                return JsonResponser::send(true, 'Transaction not found.', [], 404);
            }

            $data = $this->paystack->verify($request->reference);
            $transaction = $this->reconciliation->reconcile($request->reference, $data);

            $billing = BillingLog::with(['billingLogDetails', 'transactions'])->find($transaction->billing_id);

            return JsonResponser::send(false, 'Payment verified', [
                'transaction' => $transaction,
                'billing'     => $billing,
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 500, $th);
        }
    }
}
