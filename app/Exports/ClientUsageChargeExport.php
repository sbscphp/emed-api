<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

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
        return [
            $charge->id,
            $charge->tenant->name ?? 'N/A',
            $charge->usageFee->name ?? 'N/A',
            $charge->billing_month ? $charge->billing_month->format('F Y') : 'N/A',
            $charge->total_visits,
            number_format($charge->fee_per_visit, 2),
            number_format($charge->total_amount, 2),
            $charge->status,
            $charge->updatedBy ? ($charge->updatedBy->firstname . ' ' . $charge->updatedBy->lastname) : 'N/A',
            $charge->created_at ? $charge->created_at->format('Y-m-d H:i:s') : 'N/A',
            $charge->updated_at ? $charge->updated_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Hospital / Client',
            'Usage Fee Plan',
            'Billing Month',
            'Total Visits',
            'Fee Per Visit (₦)',
            'Total Amount (₦)',
            'Status',
            'Updated By',
            'Generated At',
            'Last Updated At',
        ];
    }
}
