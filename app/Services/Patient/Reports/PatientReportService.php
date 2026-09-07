<?php

namespace App\Services\Patient\Reports;

use App\Models\BillingLog;
use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\Radiology;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Class PatientReportService
 *
 * Turns a released result into the PDF the app offers under "Download PDF", and
 * a settled bill into the receipt it offers under "Download Receipt".
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
     * The rendered receipt for a settled bill, as raw PDF bytes.
     *
     * Rendered rather than stored, for the same reason the diagnostic reports
     * are: nothing in the schema holds a receipt file, and one built from the
     * invoice and the payments that cleared against it is always the current
     * one. A bill part paid by a friend and finished by the patient therefore
     * reads as a single receipt carrying both payments.
     *
     * The bill is expected to arrive decorated by PatientBillingService — with
     * its `payments`, its computed totals and its service title already on it —
     * because those are what the receipt prints.
     *
     * @param  \App\Models\BillingLog  $bill
     * @param  \App\Models\Tenant  $tenant
     * @param  \App\Models\Patient  $patient
     * @return string
     */
    public function receipt(BillingLog $bill, Tenant $tenant, Patient $patient): string
    {
        return $this->remember($this->receiptCacheKey($bill), function () use ($bill, $tenant, $patient) {
            return $this->render('exports.patient.receipt', [
                'bill' => $bill,
                'tenant' => $tenant,
                'patient' => $patient,
                'items' => collect($bill->billingLogDetails ?: []),
                'payments' => collect($bill->payments ?: []),
                'currency' => config('services.paystack.currency', 'NGN'),
                'total' => round((float) $bill->computed_grand_total, 2),
                'paid' => round((float) $bill->amount_paid, 2),
                'outstanding' => round((float) $bill->computed_outstanding, 2),
                'discount' => round((float) $bill->discount, 2),
                'tax' => round((float) $bill->tax_amount, 2),
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
     * Cache a receipt against the last thing that could have changed it: the
     * invoice itself, or a payment landing on it. A second payment therefore
     * produces a new key rather than the earlier receipt being served again.
     */
    protected function receiptCacheKey(BillingLog $bill): string
    {
        $touched = collect($bill->payments ?: [])
            ->map(fn($payment) => optional($payment->updated_at)->timestamp)
            ->push(optional($bill->updated_at)->timestamp)
            ->filter()
            ->max();

        return sprintf('patient-receipt:%d:%s', $bill->id, $touched ?: '0');
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
