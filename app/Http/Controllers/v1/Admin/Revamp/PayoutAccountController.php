<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\PayoutAccountRequest;
use App\Models\Tenant;
use App\Responser\JsonResponser;
use App\Services\Billing\HospitalPayoutAccountService;
use App\Services\Payment\PaystackException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Where a hospital says which bank account it should be settled into.
 *
 * Patients pay the platform's Paystack account, and Paystack splits each charge
 * to the hospital that raised the bill. That split needs a subaccount, and a
 * subaccount needs bank details — which is the whole of what this controller
 * collects.
 *
 * No API keys are involved. A hospital never holds Paystack credentials; it
 * holds a bank account, and the platform holds the keys.
 *
 * The account number is confirmed with the bank before it is stored, so the name
 * a hospital ends up saving is the one the bank returned.
 */
class PayoutAccountController extends Controller
{
    public function __construct(protected HospitalPayoutAccountService $payoutAccounts) {}

    /**
     * GET /v1/admin/billing/payout-account
     *
     * The hospital's current arrangement, and whether patients can pay it
     * online yet.
     */
    public function show(Request $request)
    {
        try {
            $tenant = $this->tenant($request);
            $account = $this->payoutAccounts->forTenant($tenant);

            return JsonResponser::send(false, 'Record found successfully', [
                'is_configured' => (bool) ($account && $account->is_ready),
                'account' => $account ? $this->present($account) : null,
            ], 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * GET /v1/admin/billing/payout-account/banks
     *
     * The banks to choose from, for the dropdown on the form.
     */
    public function banks(Request $request)
    {
        try {
            return JsonResponser::send(
                false,
                'Record(s) found successfully',
                $this->payoutAccounts->banks($request->query('country')),
                200
            );
        } catch (PaystackException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->getCode());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * POST /v1/admin/billing/payout-account/resolve
     *
     * Who the bank says an account number belongs to, so the hospital can check
     * the name before committing to it.
     */
    public function resolve(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank_code' => ['required', 'string', 'max:20'],
                'account_number' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            ]);

            return JsonResponser::send(
                false,
                'Account resolved successfully',
                $this->payoutAccounts->resolve($validated['account_number'], $validated['bank_code']),
                200
            );
        } catch (PaystackException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->getCode());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * PUT /v1/admin/billing/payout-account
     *
     * Save the account and register it with Paystack.
     *
     * A save whose Paystack registration failed still answers 200 with the
     * details stored and `is_configured` false — the hospital has told us their
     * account, and what remains is a retry rather than a re-entry. The reason is
     * on the record for them to read.
     */
    public function update(PayoutAccountRequest $request)
    {
        try {
            $tenant = $this->tenant($request);
            $account = $this->payoutAccounts->save($tenant, $request->validated());

            return JsonResponser::send(
                false,
                $account->is_ready
                    ? 'Payout account saved successfully'
                    : 'Payout account saved, but it could not be registered for online payment yet',
                [
                    'is_configured' => $account->is_ready,
                    'account' => $this->present($account),
                ],
                200
            );
        } catch (PaystackException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->getCode());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * POST /v1/admin/billing/payout-account/retry
     *
     * Push details Paystack rejected at them again, without asking the hospital
     * to type everything a second time.
     */
    public function retry(Request $request)
    {
        try {
            $tenant = $this->tenant($request);
            $account = $this->payoutAccounts->forTenant($tenant);

            if (!$account) {
                return JsonResponser::send(true, 'No payout account has been saved yet.', [], 404);
            }

            $account = $this->payoutAccounts->syncWithPaystack($tenant, $account);

            return JsonResponser::send(
                false,
                $account->is_ready ? 'Payout account registered successfully' : 'It still could not be registered',
                [
                    'is_configured' => $account->is_ready,
                    'account' => $this->present($account),
                ],
                200
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * The hospital this request is about.
     */
    protected function tenant(Request $request): Tenant
    {
        $tenant = Tenant::where('uuid', $request->header('X-Tenant-ID'))->first();

        if (!$tenant) {
            abort(response()->json([
                'error' => true,
                'message' => 'Invalid hospital selected.',
                'data' => [],
            ], 404));
        }

        return $tenant;
    }

    /**
     * What is shown back.
     *
     * The account number is returned in full: the hospital needs to read back
     * the account it is being settled into, and anybody who can reach this
     * endpoint can change that account anyway.
     *
     * @return array<string, mixed>
     */
    protected function present($account): array
    {
        return [
            'bank_code' => $account->bank_code,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_name' => $account->account_name,
            'business_name' => $account->business_name,
            'commission_percent' => (float) $account->commission_percent,
            'hospital_share_percent' => $account->hospitalSharePercent(),
            'status' => $account->status,
            'last_error' => $account->last_error,
            'verified_at' => optional($account->verified_at)->toDateTimeString(),
        ];
    }
}
