<?php

namespace App\Helpers;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportHelper
{
    public static function streamCsv($data, ?array $headers = null, ?string $fileName = null): StreamedResponse
    {
        if (empty($data)) {
            throw new \Exception("No data provided for export.");
        }

        $data = collect($data);
        $fileName = $fileName ?? 'export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            if (!$headers) {
                $firstRow = $data->first();
                if (!$firstRow || (!is_array($firstRow) && !is_object($firstRow))) {
                    throw new \Exception("Invalid data format for export.");
                }
                $headers = array_keys(is_array($firstRow) ? $firstRow : get_object_vars($firstRow));
            }

            fputcsv($handle, $headers);

            foreach ($data as $row) {
                $row = is_object($row) ? get_object_vars($row) : $row;

                $rowData = [];
                foreach ($headers as $header) {
                    $rowData[] = $row[$header] ?? '';
                }

                fputcsv($handle, $rowData);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public static function downloadPdf($data, $filename = 'export.pdf')
    {
        $pdf = PDF::loadView('exports.patients', ['patients' => $data]);
        return $pdf->download($filename);
    }
}
