<?php

namespace App\Services\Billing;

use App\Enums\GeneralEnums;
use App\Models\ConsultantRateCard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Resolves and manages consultant consultation pricing.
 *
 * A consultant may have their own rate card; full-time/salaried doctors (and any
 * doctor without a card) fall back to the shared default (user_id null, is_default).
 * The charge is the first-visit or returning fee plus an admin markup (fixed or %).
 */
class ConsultantRateService
{
    private function tenantId(): ?string
    {
        return app()->bound('currentTenant') ? app('currentTenant')->uuid : null;
    }

    /**
     * Resolve the consultation charge for a doctor.
     *
     * @return array{fee: float, markup: float, total: float, rate_card: ?ConsultantRateCard}
     */
    public function resolveFor(?int $doctorId, bool $isFirstVisit): array
    {
        $card = null;

        if ($doctorId) {
            $card = ConsultantRateCard::where('user_id', $doctorId)
                ->where('status', GeneralEnums::ACTIVE->value)
                ->first();
        }

        if (!$card) {
            $card = $this->defaultCard();
        }

        if (!$card) {
            Log::warning('No consultant rate card (or default) found; consultation charged 0.', [
                'doctor_id' => $doctorId,
            ]);

            return ['fee' => 0.0, 'markup' => 0.0, 'total' => 0.0, 'rate_card' => null];
        }

        $fee = (float) ($isFirstVisit ? $card->first_visit_price : $card->returning_price);

        $markup = $card->markup_type === 'percentage'
            ? round($fee * ((float) $card->markup_value) / 100, 2)
            : (float) $card->markup_value;

        return [
            'fee'       => $fee,
            'markup'    => $markup,
            'total'     => round($fee + $markup, 2),
            'rate_card' => $card,
        ];
    }

    public function defaultCard(): ?ConsultantRateCard
    {
        return ConsultantRateCard::where('is_default', true)
            ->where('status', GeneralEnums::ACTIVE->value)
            ->first();
    }

    public function forUser(int $userId): ?ConsultantRateCard
    {
        return ConsultantRateCard::where('user_id', $userId)->first();
    }

    /**
     * Create/update a specific consultant's rate card.
     */
    public function upsertForUser(int $userId, array $data): ConsultantRateCard
    {
        return ConsultantRateCard::updateOrCreate(
            ['user_id' => $userId],
            [
                'tenant_id'         => $this->tenantId(),
                'first_visit_price' => $data['first_visit_price'] ?? 0,
                'returning_price'   => $data['returning_price'] ?? 0,
                'markup_type'       => $data['markup_type'] ?? 'fixed',
                'markup_value'      => $data['markup_value'] ?? 0,
                'is_default'        => false,
                'status'            => $data['status'] ?? GeneralEnums::ACTIVE->value,
            ],
        );
    }

    /**
     * Create/update the shared default rate card (user_id null).
     */
    public function setDefault(array $data): ConsultantRateCard
    {
        $default = $this->defaultCard() ?? new ConsultantRateCard();

        $default->fill([
            'tenant_id'         => $this->tenantId(),
            'user_id'           => null,
            'first_visit_price' => $data['first_visit_price'] ?? 0,
            'returning_price'   => $data['returning_price'] ?? 0,
            'markup_type'       => $data['markup_type'] ?? 'fixed',
            'markup_value'      => $data['markup_value'] ?? 0,
            'is_default'        => true,
            'status'            => $data['status'] ?? GeneralEnums::ACTIVE->value,
        ]);
        $default->save();

        return $default;
    }
}
