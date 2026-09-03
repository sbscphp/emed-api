<?php

namespace App\Services\Patient\Reports;

use App\Models\Laboratory;
use App\Models\Radiology;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Class PatientReportService
 *
 * Turns a released result into the PDF the app offers under "Download PDF".
 *
 * The report is rendered rather than fetched: nothing in the schema stores a
 * file for a laboratory or radiology result, the result *is* the rows the lab
 * entered, and a report built from them is always the current one. That also
 * means the file name, its size and its page count are only knowable by
 * rendering it, which is why the detail screen's document card and its download
 * button are served by the same method — the render is cached for half an hour
 * against the moment the result last changed, so opening a result and then
 * downloading it renders once rather than twice.
 */
class PatientReportService
{
    /**
     * How long a rendered report stays cached.
     */
    protected const CACHE_MINUTES = 30;

    /**
     * The rendered laboratory report, as raw PDF bytes.
     *
     * @param  \App\Models\Laboratory  $order
     * @param  \App\Models\Tenant  $tenant
     * @return string
     */
    public function laboratory(Laboratory $order, Tenant $tenant): string
    {
        return $this->remember($this->cacheKey('lab', $order), function () use ($order, $tenant) {
            return $this->render('exports.patient.lab_report', [
                'order' => $order,
                'tenant' => $tenant,
                'results' => $order->results->sortBy('display_order'),
                'patient' => $order->patient,
            ]);
        });
    }

    /**
     * The rendered radiology report, as raw PDF bytes.
     *
     * @param  \App\Models\Radiology  $order
     * @param  \App\Models\Tenant  $tenant
     * @return string
     */
    public function radiology(Radiology $order, Tenant $tenant): string
    {
        return $this->remember($this->cacheKey('radiology', $order), function () use ($order, $tenant) {
            return $this->render('exports.patient.radiology_report', [
                'order' => $order,
                'tenant' => $tenant,
                'results' => $order->results,
                'patient' => $order->patient,
            ]);
        });
    }

    /**
     * The document card above a released result: what the file is called, how
     * big it is and how many pages it runs to.
     *
     * @param  string  $pdf  the rendered bytes
     * @param  string  $fileName
     * @return array<string, mixed>
     */
    public function documentMeta(string $pdf, string $fileName): array
    {
        return [
            'file_name' => $fileName,
            'file_size' => $this->humanSize(strlen($pdf)),
            'file_size_bytes' => strlen($pdf),
            'pages' => $this->pageCount($pdf),
            'mime_type' => 'application/pdf',
            'type' => 'PDF Report',
        ];
    }

    /**
     * The file name a report downloads as, built from the test it reports on.
     *
     * @param  string|null  $testName
     * @param  string  $suffix
     * @return string
     */
    public function fileName(?string $testName, string $suffix = 'Report'): string
    {
        $base = Str::of($testName ?: 'Result')
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')
            ->squish()
            ->replace(' ', '_')
            ->trim();

        $base = $base->isEmpty() ? 'Result' : (string) $base;

        return $base . '_' . $suffix . '.pdf';
    }

    /**
     * Render a report view to PDF bytes.
     *
     * @param  string  $view
     * @param  array<string, mixed>  $data
     * @return string
     */
    protected function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->output();
    }

    /**
     * How many pages the rendered document runs to.
     *
     * Read off the document's own page tree, which states its count, rather than
     * by laying the document out a second time. Any failure answers 1 rather
     * than bringing a screen down over a number printed under an icon.
     */
    protected function pageCount(string $pdf): int
    {
        if (preg_match('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $pdf, $matches)) {
            return max(1, (int) $matches[1]);
        }

        // Dompdf writes an uncompressed page tree, so the fallback only matters
        // if that ever changes.
        return max(1, substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages'));
    }

    /**
     * Present a byte count the way the document card reads it.
     */
    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Cache a render against the moment the result it reports on last changed,
     * so a corrected result is never served from a stale report.
     *
     * @param  string  $key
     * @param  \Closure  $render
     * @return string
     */
    protected function remember(string $key, \Closure $render): string
    {
        try {
            return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), $render);
        } catch (\Throwable $th) {
            // A cache store that cannot hold the bytes must not cost the patient
            // their download.
            return $render();
        }
    }

    /**
     * @param  string  $kind
     * @param  \App\Models\Laboratory|\App\Models\Radiology  $order
     * @return string
     */
    protected function cacheKey(string $kind, $order): string
    {
        $touched = $order->results
            ->map(fn($result) => optional($result->updated_at)->timestamp)
            ->push(optional($order->updated_at)->timestamp)
            ->filter()
            ->max();

        return sprintf('patient-report:%s:%d:%s', $kind, $order->id, $touched ?: '0');
    }
}
