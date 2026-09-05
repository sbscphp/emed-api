<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Authoritative Paystack reconciliation. Paystack cannot send our `X-Tenant-ID`
 * header, so the tenant is resolved from the transaction metadata (set at
 * initialize time) and made current before the ledger is touched.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(private PaymentReconciliationService $reconciliation) {}

    public function handle(Request $request)
    {
        $secret = config('services.paystack.secret');
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        // Verify the event actually came from Paystack.
        if (!$secret || !$signature || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            return response()->json(['status' => false, 'message' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true) ?: [];
        $type = $event['event'] ?? null;
        $data = $event['data'] ?? [];

        // We only act on successful charges; acknowledge everything else with 200.
        if ($type !== 'charge.success') {
            return response()->json(['status' => true]);
        }

        try {
            $tenantUuid = $data['metadata']['tenant'] ?? null;
            $reference = $data['reference'] ?? null;

            if (!$tenantUuid || !$reference) {
                Log::warning('Paystack webhook missing tenant/reference metadata.', ['reference' => $reference]);
                return response()->json(['status' => true]);
            }

            $tenant = Tenant::where('uuid', $tenantUuid)->first();
            if (!$tenant) {
                Log::warning('Paystack webhook for unknown tenant.', ['tenant' => $tenantUuid]);
                return response()->json(['status' => true]);
            }

            // Switch to the tenant DB, then reconcile idempotently.
            $tenant->makeCurrent();
            $this->reconciliation->reconcile($reference, $data);
        } catch (\Throwable $th) {
            Log::error('Paystack webhook processing failed.', [
                'reference' => $data['reference'] ?? null,
                'exception' => $th->getMessage(),
            ]);
            // Still return 200 so Paystack does not hammer us; verify-on-return is the backstop.
        }

        return response()->json(['status' => true]);
    }
}
