<?php

namespace App\Exports;

use App\Models\UsageFee;
use App\Services\SuperAdmin\ClientManagement\VisitUsageService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * A client's monthly usage charges, as a spreadsheet.
 *
 * One row per billed month, matching the Hospital visits table column for
 * column. "Visits billed" is the count the fee was multiplied by, which under a
 * unique-visit plan is one per patient rather than one per trip — the visit type
 * column is there so a smaller number than expected explains itself.
 *
 * @see \App\Services\SuperAdmin\ClientManagement\ClientManagementService::showClientVisitCharges()
 */
class ClientUsageChargeExport implements FromCollection, WithHeadings, WithMapping
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records;
    }

    public function map($charge): array
    {
        $fee = $charge->usageFee;

        return [
            $charge->id,
            $charge->tenant->name ?? 'N/A',
            $charge->billing_month ? $charge->billing_month->format('F Y') : 'N/A',
            $fee?->billing_mode === UsageFee::UNIQUE ? VisitUsageService::UNIQUE : VisitUsageService::GENERAL,
            $fee->name ?? 'N/A',
            $charge->total_visits,
            number_format($charge->fee_per_visit, 2),
            number_format($charge->total_amount, 2),
            $charge->status,
            $charge->updatedBy
                ? trim(($charge->updatedBy->fullname ?: $charge->updatedBy->first_name . ' ' . $charge->updatedBy->last_name))
                : 'N/A',
            $charge->created_at ? $charge->created_at->format('Y-m-d H:i:s') : 'N/A',
            $charge->updated_at ? $charge->updated_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Hospital / Client',
            'Billing Month',
            'Visit type',
            'Usage Fee Plan',
            'Visits billed',
            'Fee Per Visit (N)',
            'Total Amount (N)',
            'Payment status',
            'Updated By',
            'Generated At',
            'Last Updated At',
        ];
    }
}
