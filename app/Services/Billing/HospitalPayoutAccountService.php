<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Services\Payment\PaystackException;
use App\Services\Payment\PaystackService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Class HospitalPayoutAccountService
 *
 * Where a hospital says which bank account its share of a patient payment
 * should land in.
 *
 * Patients pay the platform's Paystack account, so a hospital needs a way to be
 * paid out of it without ever holding an API key. That way is a Paystack
 * subaccount: the bank details saved here are pushed to Paystack once, and every
 * charge the patient app raises for this hospital is initialized against the
 * subaccount code that comes back. Paystack then splits at settlement.
 *
 * The account number is resolved with the bank before it is stored, so what a
 * hospital saves is the name the bank returned rather than one they typed. A
 * transposed digit is caught here rather than at the first settlement.
 */
class HospitalPayoutAccountService
{
    public function __construct(protected PaystackService $paystack) {}

    /**
     * The hospital's current arrangement, or null if it has never saved one.
     */
    public function forTenant(Tenant $tenant): ?TenantPayoutAccount
    {
        return TenantPayoutAccount::where('tenant_id', $tenant->id)->first();
    }

    /**
     * The banks the hospital can choose from.
     *
     * @return array<int, array<string, string>>
     *
     * @throws \App\Services\Payment\PaystackException
     */
    public function banks(?string $country = null): array
    {
        return collect($this->paystack->listBanks($country))
            ->map(fn($bank) => [
                'name' => $bank['name'] ?? null,
                'code' => $bank['code'] ?? null,
                'slug' => $bank['slug'] ?? null,
                'currency' => $bank['currency'] ?? null,
            ])
            ->filter(fn($bank) => !empty($bank['code']))
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Who a bank says an account number belongs to.
     *
     * Offered on its own as well as being run during a save, so the hospital
     * sees the name before it commits to it.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Services\Payment\PaystackException
     */
    public function resolve(string $accountNumber, string $bankCode): array
    {
        $resolved = $this->paystack->resolveAccountNumber($accountNumber, $bankCode);

        return [
            'account_number' => $resolved['account_number'] ?? $accountNumber,
            'account_name' => $resolved['account_name'] ?? null,
            'bank_code' => $bankCode,
        ];
    }

    /**
     * Save where this hospital is settled, and register it with Paystack.
     *
     * The row is written whichever way the Paystack call goes. A hospital whose
     * subaccount could not be created has still told us their details, and the
     * failure is theirs to see and retry — losing the details as well would make
     * them type everything again to find out what was wrong.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \App\Services\Payment\PaystackException
     */
    public function save(Tenant $tenant, array $data): TenantPayoutAccount
    {
        // Resolved first: there is no point registering a subaccount against an
        // account number the bank does not recognise.
        $resolved = $this->resolve($data['account_number'], $data['bank_code']);

        if (empty($resolved['account_name'])) {
            throw new PaystackException('We could not confirm that account number with the bank.', [], 422);
        }

        $account = TenantPayoutAccount::firstOrNew(['tenant_id' => $tenant->id]);

        $account->fill([
            'bank_code' => $data['bank_code'],
            'bank_name' => $data['bank_name'] ?? $account->bank_name,
            'account_number' => $resolved['account_number'],
            'account_name' => $resolved['account_name'],
            'business_name' => $data['business_name'] ?? $tenant->name,
            'commission_percent' => $data['commission_percent']
                ?? $account->commission_percent
                ?? config('services.paystack.commission_percent', 0),
            'updated_by' => optional(Auth::user())->id,
        ]);

        if (!$account->exists) {
            $account->created_by = optional(Auth::user())->id;
        }

        $account->save();

        return $this->syncWithPaystack($tenant, $account);
    }

    /**
     * Push the saved details to Paystack, creating the subaccount or moving the
     * existing one onto them.
     *
     * Also the retry path: a hospital whose first attempt failed calls this
     * again rather than re-entering details Paystack never accepted.
     */
    public function syncWithPaystack(Tenant $tenant, TenantPayoutAccount $account): TenantPayoutAccount
    {
        $payload = [
            'business_name' => $account->business_name ?: $tenant->name,
            'bank_code' => $account->bank_code,
            'account_number' => $account->account_number,

            // Paystack's percentage_charge is the share the subaccount keeps,
            // which is the inverse of the commission the platform is configured
            // to take.
            'percentage_charge' => $account->hospitalSharePercent(),
            'email' => $tenant->email,
            'phone' => $tenant->phone_number,
        ];

        try {
            $subaccount = $this->pushToPaystack($account, $payload);

            $account->forceFill([
                'subaccount_code' => $subaccount['subaccount_code'] ?? $account->subaccount_code,
                'bank_name' => $subaccount['settlement_bank'] ?? $account->bank_name,
                'status' => 'Active',
                'last_error' => null,
                'verified_at' => now(),
            ])->save();
        } catch (PaystackException $th) {
            // Kept rather than rethrown: the details are saved, and the hospital
            // is shown why the registration did not go through so they can fix
            // it and retry.
            $account->forceFill([
                'status' => 'Failed',
                'last_error' => $th->getMessage(),
            ])->save();

            Log::warning('Could not register a hospital subaccount with Paystack.', [
                'tenant_id' => $tenant->id,
                'message' => $th->getMessage(),
            ]);
        }

        return $account->fresh();
    }

    /**
     * Create the subaccount, or move the existing one onto these details.
     *
     * The awkward case is a stored code Paystack does not recognise, which is
     * what happens when the platform's keys are rotated or pointed at a
     * different business: the code is well formed and saved, and the integration
     * now holding the keys has never heard of it. Updating it can only ever fail
     * from then on, so a not-found update is followed by creating a fresh one
     * under the current integration. Without that, a hospital whose account was
     * registered before a key change can never be paid again and no amount of
     * retrying helps.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \App\Services\Payment\PaystackException
     */
    protected function pushToPaystack(TenantPayoutAccount $account, array $payload): array
    {
        if (empty($account->subaccount_code)) {
            return $this->paystack->createSubaccount($payload);
        }

        try {
            return $this->paystack->updateSubaccount($account->subaccount_code, $payload);
        } catch (PaystackException $th) {
            if (!$th->isNotFound()) {
                throw $th;
            }

            Log::warning('A stored Paystack subaccount is unknown to the current keys; creating a new one.', [
                'tenant_id' => $account->tenant_id,
                'orphaned_subaccount' => $account->subaccount_code,
                'message' => $th->getMessage(),
            ]);

            return $this->paystack->createSubaccount($payload);
        }
    }

    /**
     * The subaccount a charge for this hospital should be split to.
     *
     * Null when the hospital has not finished setting up, which is what the
     * payment services check before they let a patient start a checkout.
     */
    public function subaccountCodeFor(Tenant $tenant): ?string
    {
        $account = $this->forTenant($tenant);

        return $account && $account->is_ready ? $account->subaccount_code : null;
    }
}
