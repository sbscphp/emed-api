<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Services\Patient\Billing\PaymentSupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The page a shared payment link opens.
 *
 * This is the web half of the support flow: a patient generates a link in the
 * app and sends it to somebody they trust, who opens it in a browser, sees what
 * is being asked for, picks an amount, pays on Paystack and comes back here.
 *
 * Server rendered rather than a JSON client, and for a reason worth stating: the
 * person opening it is not a user of anything. They have no account, no app and
 * no token, and they may well be on a slow phone. A page that renders in one
 * request works for them; a page that boots a client to fetch its own contents
 * does not, and gains nothing here.
 *
 * It calls the same PaymentSupportService the public API calls, so what is shown
 * here and what /api/v1/support/{token} answers cannot drift apart — including
 * the rule that what a supporter may see is whatever publicView() builds and
 * nothing else.
 */
class PaymentSupportPageController extends Controller
{
    public function __construct(protected PaymentSupportService $supportService) {}

    /**
     * GET /payment-support/{token}
     *
     * What the friend sees when they open the link.
     */
    public function show(string $token)
    {
        try {
            ['tenant' => $tenant, 'request' => $request] = $this->supportService->resolveToken($token);

            return view('payment-support.show', [
                'support' => $this->supportService->publicView($tenant, $request),
                'token' => $token,
            ]);
        } catch (PatientAppException $th) {
            return response()->view('payment-support.unavailable', [
                'message' => $th->getMessage(),
            ], $th->status());
        } catch (Throwable $th) {
            Log::error('The payment support page could not be rendered.', [
                'token' => $token,
                'exception' => $th->getMessage(),
            ]);

            return response()->view('payment-support.unavailable', [
                'message' => 'We could not open that support link. Please try again shortly.',
            ], 500);
        }
    }

    /**
     * POST /payment-support/{token}/contribute
     *
     * Start the payment and hand the browser to Paystack.
     *
     * Validation is deliberately light here — the amount is bounded against what
     * the bill still needs inside the service, which is the only place that can
     * be checked safely.
     */
    public function contribute(Request $request, string $token)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        try {
            $checkout = $this->supportService->contribute($token, $validated);

            if (empty($checkout['authorization_url'])) {
                return back()->withInput()->with('error', 'We could not start that payment. Please try again.');
            }

            // Away to Paystack. Nothing has been paid yet; the row is Pending
            // until the callback below verifies it.
            return redirect()->away($checkout['authorization_url']);
        } catch (PatientAppException $th) {
            return back()->withInput()->with('error', $th->getMessage());
        } catch (Throwable $th) {
            Log::error('A support contribution could not be started.', [
                'token' => $token,
                'exception' => $th->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Something went wrong starting that payment. Please try again.');
        }
    }

    /**
     * GET /payment-support/{token}/callback
     *
     * Where Paystack sends the supporter back to.
     *
     * The redirect proves nothing on its own, so the reference on it is verified
     * against Paystack before anything is called successful. A supporter who
     * closes the tab instead of returning is not lost either — the payment row
     * keeps the reference and the same verification settles it whenever it is
     * next run.
     */
    public function callback(Request $request, string $token)
    {
        // Paystack sends both; either is the reference.
        $reference = $request->query('reference') ?: $request->query('trxref');

        if (empty($reference)) {
            return redirect()->route('payment-support.show', $token)
                ->with('error', 'That payment did not complete.');
        }

        try {
            $result = $this->supportService->verifyContribution($token, $reference);

            return view('payment-support.result', [
                'settled' => (bool) $result['settled'],
                'amount' => (float) $result['payment']->amount,
                'reference' => $result['payment']->reference,
                'support' => $result['support'],
                'token' => $token,
            ]);
        } catch (PatientAppException $th) {
            return response()->view('payment-support.unavailable', [
                'message' => $th->getMessage(),
            ], $th->status());
        } catch (Throwable $th) {
            Log::error('A support contribution could not be confirmed.', [
                'token' => $token,
                'reference' => $reference,
                'exception' => $th->getMessage(),
            ]);

            return response()->view('payment-support.unavailable', [
                'message' => 'We could not confirm that payment. If you were charged, it will still be applied.',
            ], 500);
        }
    }
}
