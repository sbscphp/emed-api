<?php

namespace App\Helpers;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportHelper
{
    protected static function cleanUtf8($value)
    {
        if (is_string($value)) {
            // First, try to convert to UTF-8 if not already
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            // Remove any remaining invalid characters
            $value = iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $value);
            // Remove any non-printable characters except newlines and tabs
            $value = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/', '', $value);
        } elseif (is_array($value) || is_object($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::cleanUtf8($v);
            }
        }
        return $value;
    }

    public static function streamCsv($data, ?array $headers = null, ?string $fileName = null): StreamedResponse
    {
        if (empty($data)) {
            throw new \Exception("No data provided for export.");
        }

        $data = collect($data);
        $fileName = $fileName ?? 'export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for proper UTF-8 handling in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            if (!$headers) {
                $firstRow = $data->first();
                if (!$firstRow || (!is_array($firstRow) && !is_object($firstRow))) {
                    throw new \Exception("Invalid data format for export.");
                }
                $firstRow = is_object($firstRow) ? get_object_vars($firstRow) : $firstRow;
                $headers = array_map('self::cleanUtf8', array_keys($firstRow));
            }

            // Clean headers
            $headers = array_map('self::cleanUtf8', $headers);
            fputcsv($handle, $headers);

            foreach ($data as $row) {
                $row = is_object($row) ? get_object_vars($row) : $row;
                $row = self::cleanUtf8($row);

                $rowData = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    $rowData[] = $value;
                }

                fputcsv($handle, $rowData);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public static function downloadPdf($data, $filename = 'export.pdf')
    {
        // Clean data before passing to PDF
        $cleanedData = is_array($data) 
            ? array_map('self::cleanUtf8', $data) 
            : self::cleanUtf8($data);
            
        $pdf = PDF::loadView('exports.patients', ['patients' => $cleanedData]);
        return $pdf->download($filename);
    }
}
