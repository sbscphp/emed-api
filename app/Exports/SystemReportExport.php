<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SystemReportExport implements FromCollection, WithHeadings
{
    protected $report;

    /**
     * Create a new instance with the report data.
     *
     * @param array $report
     */
    public function __construct($report)
    {
        $this->report = $report;
    }

    /**
     * Return the collection of data to be exported.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->report)->map(function ($admin) {
            return [
                'Full Name' => $admin['full_name'],
                'Roles' => $admin['roles']->join(', '),
                'Status' => $admin['status'],
                'Actions' => $admin['actions']->map(function ($action) {
                    return $action['description'] . ' (Performed at: ' . $action['performed_at'] . ')';
                })->join("\n"),
            ];
        });
    }

    /**
     * Headings for the Excel file.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Full Name',
            'Roles',
            'Status',
            'Actions',
        ];
    }
}
