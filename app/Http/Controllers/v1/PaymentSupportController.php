<?php

namespace App\Http\Controllers\v1;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Billing\ContributeRequest;
use App\Responser\JsonResponser;
use App\Services\Patient\Billing\PaymentSupportService;
use Throwable;

/**
 * The public side of a shared payment link.
 *
 * These are the only patient-billing endpoints that run without a token, because
 * the person calling them is a friend of the patient with no account and no
 * hospital of their own. Everything that a token would normally establish is
 * therefore established by the link itself: the token names the request, the
 * request names the bill, and the bill names the hospital.
 *
 * What a caller can learn is bounded by PaymentSupportService::publicView(),
 * which builds the payload field by field — a first name, the service the bill
 * is for, the hospital and the amounts. Nothing about the patient's record can
 * reach this controller.
 *
 * Throttled, because an unauthenticated endpoint that resolves a token is worth
 * guessing at.
 */
class PaymentSupportController extends Controller
{
    public function __construct(protected PaymentSupportService $supportService) {}

    /**
     * GET /v1/support/{token}
     *
     * What the friend sees when they open the link.
     */
    public function show($token)
    {
        try {
            ['tenant' => $tenant, 'request' => $request] = $this->supportService->resolveToken($token);

            return JsonResponser::send(
                false,
                'Support request retrieved successfully.',
                $this->supportService->publicView($tenant, $request),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * POST /v1/support/{token}/contribute
     *
     * Start a contribution. Answers with the Paystack page to send the payer to;
     * nothing has been paid at this point.
     */
    public function contribute(ContributeRequest $request, $token)
    {
        try {
            $checkout = $this->supportService->contribute($token, $request->validated());

            return JsonResponser::send(false, 'Payment initiated successfully.', [
                'reference' => $checkout['reference'],
                'authorization_url' => $checkout['authorization_url'],
                'access_code' => $checkout['access_code'],
                'public_key' => $checkout['public_key'],
                'amount' => $checkout['amount'],
                'currency' => $checkout['currency'],
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/support/{token}/verify/{reference}
     *
     * Confirm a contribution once the payer comes back from Paystack, and answer
     * with the progress the page should now show.
     *
     * The reference is checked against the link before it is verified, so a
     * reference from another bill cannot be confirmed through somebody else's.
     */
    public function verify($token, $reference)
    {
        try {
            $result = $this->supportService->verifyContribution($token, $reference);

            return JsonResponser::send(
                false,
                $result['settled'] ? 'Thank you. Your payment was successful.' : 'This payment has not been completed.',
                [
                    'settled' => $result['settled'],
                    'already_settled' => $result['already_settled'],
                    'amount' => round((float) $result['payment']->amount, 2),
                    'reference' => $result['payment']->reference,
                    'support' => $result['support'],
                ],
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
