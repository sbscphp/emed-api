<?php

namespace App\Services\Revamp;

use App\Enums\BillingTypeEnum;
use App\Enums\GeneralEnums;
use App\Enums\TransactionStatusEnum;
use App\Helpers\GeneralHelper;
use App\Enums\ListModuleEnums;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\Patient;
use App\Models\RateCardItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Standalone (patient-only, no clinical visit) manual billing: the billing manager
 * raises an invoice, adds line items (picked from the rate card or typed in), ties
 * it to a patient, and can later view/edit it. Payment is handled separately by the
 * PaymentReconciliationService.
 */
class ManualBillingService
{
    private function tenantId(): ?string
    {
        return app()->bound('currentTenant') ? app('currentTenant')->uuid : null;
    }

    public function create(array $data): BillingLog
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $patient = Patient::findOrFail($data['patient_id']);
            $lines   = $this->resolveLines($data['items']);

            $itemsTotal = array_sum(array_column($lines, 'line_amount'));
            $discount   = (float) ($data['discount'] ?? 0);
            $taxAmount  = (float) ($data['tax_amount'] ?? 0);
            // Grand total is VAT-inclusive: subtotal - discount + VAT.
            $grandTotal = max(0, $itemsTotal - $discount + $taxAmount);

            $billing = BillingLog::create([
                'tenant_id'          => $this->tenantId(),
                'created_by'         => Auth::id(),
                'patient_id'         => $patient->id,
                'patient_name'       => trim($patient->firstname . ' ' . $patient->lastname),
                'invoice_number'     => $this->generateInvoiceNumber(),
                'type'               => BillingTypeEnum::MANUAL->value,
                'billing_date'       => $data['billing_date'] ?? now()->toDateString(),
                'discount'           => $discount,
                'tax_amount'         => $taxAmount,
                'total_amount'       => $itemsTotal,
                'grand_total'        => $grandTotal,
                'amount_paid'        => 0,
                'amount_outstanding' => $grandTotal,
                'payment_status'     => GeneralEnums::PENDING->value,
                'notes'              => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                BillingLogDetail::create([
                    'tenant_id'       => $this->tenantId(),
                    'billing_id'      => $billing->id,
                    'service_unit_id' => $line['service_unit_id'],
                    'item_name'       => $line['item_name'],
                    'quantity'        => $line['quantity'],
                    'amount'          => $line['line_amount'],
                    'amount_paid'     => 0,
                    'status'          => GeneralEnums::PENDING->value,
                ]);
            }

            $this->audit($billing, 'Create', 'Created manual billing ' . $billing->invoice_number);

            return $billing->fresh(['patient', 'billingLogDetails.serviceUnit', 'transactions']);
        });
    }

    public function update($id, array $data): BillingLog
    {
        return DB::connection('tenant')->transaction(function () use ($id, $data) {
            /** @var BillingLog $billing */
            $billing = BillingLog::where('type', BillingTypeEnum::MANUAL->value)->findOrFail($id);

            if ($billing->payment_status === GeneralEnums::PAID->value) {
                throw new \RuntimeException('A fully paid billing cannot be edited.');
            }

            // Replace line items when a fresh set is supplied.
            if (array_key_exists('items', $data)) {
                $billing->billingLogDetails()->delete();

                foreach ($this->resolveLines($data['items']) as $line) {
                    BillingLogDetail::create([
                        'tenant_id'       => $this->tenantId(),
                        'billing_id'      => $billing->id,
                        'service_unit_id' => $line['service_unit_id'],
                        'item_name'       => $line['item_name'],
                        'quantity'        => $line['quantity'],
                        'amount'          => $line['line_amount'],
                        'amount_paid'     => 0,
                        'status'          => GeneralEnums::PENDING->value,
                    ]);
                }
            }

            if (array_key_exists('discount', $data)) {
                $billing->discount = (float) $data['discount'];
            }
            if (array_key_exists('tax_amount', $data)) {
                $billing->tax_amount = (float) $data['tax_amount'];
            }
            if (array_key_exists('notes', $data)) {
                $billing->notes = $data['notes'];
            }
            if (array_key_exists('billing_date', $data)) {
                $billing->billing_date = $data['billing_date'];
            }

            // Recompute totals. grand_total is VAT-inclusive (subtotal - discount + VAT);
            // amount paid is taken from the successful payment ledger, not line-item sums,
            // so the VAT portion (which has no line item) is accounted for correctly.
            $itemsTotal = (float) $billing->billingLogDetails()->sum('amount');
            $discount   = (float) ($billing->discount ?? 0);
            $tax        = (float) ($billing->tax_amount ?? 0);
            $paid       = (float) $billing->transactions()
                ->where('status', TransactionStatusEnum::SUCCESS->value)
                ->sum('amount');

            $billing->total_amount       = $itemsTotal;
            $billing->grand_total        = max(0, $itemsTotal - $discount + $tax);
            $billing->amount_paid        = min($paid, $billing->grand_total);
            $billing->amount_outstanding = max(0, $billing->grand_total - $billing->amount_paid);
            $billing->payment_status     = $billing->amount_paid <= 0
                ? GeneralEnums::PENDING->value
                : ($billing->amount_paid < $billing->grand_total ? GeneralEnums::PART_PAID->value : GeneralEnums::PAID->value);
            $billing->save();

            $this->audit($billing, 'Update', 'Updated manual billing ' . $billing->invoice_number);

            return $billing->fresh(['patient', 'billingLogDetails.serviceUnit', 'transactions']);
        });
    }

    public function find($id): BillingLog
    {
        return BillingLog::with(['patient', 'billingLogDetails.serviceUnit', 'transactions'])
            ->where('type', BillingTypeEnum::MANUAL->value)
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $query = BillingLog::with(['patient', 'billingLogDetails.serviceUnit'])
            ->where('type', BillingTypeEnum::MANUAL->value);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('paginate', true)) {
            return $query->paginate($request->query('per_page', 20));
        }

        return $query->get();
    }

    /**
     * Turn request line items into concrete lines, drawing the name/price from the
     * rate card when referenced (a provided value overrides the catalog default).
     *
     * @return array<int, array{item_name:string, unit_price:float, quantity:int, line_amount:float, service_unit_id:int|null}>
     */
    private function resolveLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $name          = $item['item_name'] ?? null;
            $unitPrice     = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
            $serviceUnitId = $item['service_unit_id'] ?? null;

            if (!empty($item['rate_card_item_id'])) {
                $rateCardItem = RateCardItem::find($item['rate_card_item_id']);
                if ($rateCardItem) {
                    $name          = $name ?: $rateCardItem->name;
                    $unitPrice     = $unitPrice ?? (float) $rateCardItem->unit_price;
                    $serviceUnitId = $serviceUnitId ?: $rateCardItem->service_unit_id;
                }
            }

            $quantity  = (int) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($unitPrice ?? 0);

            $lines[] = [
                'item_name'       => $name ?? 'Item',
                'unit_price'      => $unitPrice,
                'quantity'        => $quantity,
                'line_amount'     => $unitPrice * $quantity,
                'service_unit_id' => $serviceUnitId,
            ];
        }

        return $lines;
    }

    private function generateInvoiceNumber(): string
    {
        $last = BillingLog::whereNotNull('invoice_number')->orderByDesc('id')->first();
        $next = $last ? ((int) filter_var($last->invoice_number, FILTER_SANITIZE_NUMBER_INT)) + 1 : 1;

        return 'INV-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function audit(BillingLog $billing, string $action, string $description): void
    {
        try {
            GeneralHelper::storeAuditLog([
                'causer_id'       => Auth::id(),
                'action_id'       => $billing->id,
                'action_type'     => BillingLog::class,
                'action'          => $action,
                'log_name'        => $description,
                'description'     => $description,
                'module_accessed' => ListModuleEnums::BILLING,
            ]);
        } catch (\Throwable $th) {
            // Auditing must never block a billing operation.
        }
    }
}
