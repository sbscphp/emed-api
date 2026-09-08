<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What a hospital is charged per patient visit, and on which rule.
 *
 * Two rules, and they are meant to be exclusive:
 *
 *   is_general_visit  every visit is charged, including the same patient
 *                     coming back later in the same month.
 *   is_unique_visit   each patient is charged once a month, however many
 *                     times they came.
 *
 * Nothing in the schema stops both being set at once, and rows exist that do.
 * `billing_mode` resolves that in one place rather than leaving each caller to
 * write its own if/else and reach a different answer.
 *
 * @see \App\Services\SuperAdmin\ClientManagement\VisitUsageService
 */
class UsageFee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $connection = 'landlord';

    protected $table = 'usage_fees';

    protected $casts = [
        'is_general_visit' => 'boolean',
        'is_unique_visit' => 'boolean',
        'amount' => 'decimal:2',
        'cycles' => 'array',
    ];

    /**
     * Charge every visit, repeat visits included.
     */
    public const GENERAL = 'general';

    /**
     * Charge each patient once per month, however often they came.
     */
    public const UNIQUE = 'unique';

    /**
     * Which rule this fee actually bills on.
     *
     * General wins when both flags are set. That is the precedence the billing
     * command has always used, so making it explicit here changes no existing
     * bill — but a fee in that state is misconfigured, and the command says so
     * rather than letting it pass silently.
     *
     * Null when neither flag is set: such a fee bills nothing, which is a
     * configuration mistake rather than a free plan, and is reported as one.
     */
    public function getBillingModeAttribute(): ?string
    {
        if ($this->is_general_visit) {
            return self::GENERAL;
        }

        return $this->is_unique_visit ? self::UNIQUE : null;
    }

    /**
     * Whether both rules are switched on, which is not a state that can be
     * billed as written.
     */
    public function getHasConflictingRulesAttribute(): bool
    {
        return $this->is_general_visit && $this->is_unique_visit;
    }

    /**
     * What one chargeable visit costs on a given cycle.
     *
     * `cycles` carries per-cycle overrides; `amount` is the fallback, and the
     * only price most fees have.
     */
    public function amountForCycle(string $cycle = 'monthly'): float
    {
        $cycles = $this->cycles;

        if (is_array($cycles) && isset($cycles[$cycle]) && $cycles[$cycle] !== null) {
            return round((float) $cycles[$cycle], 2);
        }

        return round((float) $this->amount, 2);
    }
}
