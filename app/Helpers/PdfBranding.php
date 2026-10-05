<?php

namespace App\Helpers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What every exported PDF is branded with: the hospital's name, contact line
 * and logo, plus the EMED palette the tables are drawn in.
 *
 * Shared to the export views by a view composer (see AppServiceProvider), so a
 * caller that renders `exports.*` or `reports.*` gets the branding without
 * passing it. A view that is handed a `$tenant` (the patient app's reports,
 * which render outside the tenant context) is branded with that tenant;
 * everything else is branded with the current one, and a render with no tenant
 * at all (the super admin console) falls back to EMED itself.
 */
class PdfBranding
{
    /**
     * EMED's brand purple, and the shades the documents are drawn in.
     */
    public const PRIMARY = '#6C4BF4';
    public const PRIMARY_DARK = '#2b1b6b';
    public const PRIMARY_SOFT = '#f3f0ff';

    /**
     * Who the document is attributed to when there is no hospital to name.
     */
    protected const FALLBACK_NAME = 'EMED';

    /**
     * How long a fetched logo stays cached. Logos are rarely changed and an
     * upload gets a new URL, which is a new cache key anyway.
     */
    protected const LOGO_CACHE_HOURS = 24;

    /**
     * The longest edge a logo is embedded at. Uploads are routinely several
     * megapixels, and embedding one at full size (twice, for the watermark)
     * would make every export several megabytes heavier for no visible gain.
     */
    protected const LOGO_MAX_EDGE = 360;

    /**
     * Branding already resolved during this request, by tenant id.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $resolved = [];

    /**
     * The branding a document is rendered with.
     *
     * @return array{name: string, initials: string, address: ?string, contact: ?string, logo: ?string, watermark: ?string}
     */
    public static function resolve(?Tenant $tenant = null): array
    {
        $tenant ??= self::currentTenant();
        $key = $tenant ? (string) $tenant->getKey() : 'none';

        return self::$resolved[$key] ??= self::build($tenant);
    }

    /**
     * Which colour a status value is drawn in: success, danger, warning, info
     * or neutral.
     *
     * Checked in that order of severity rather than by first match, because
     * the negative forms contain the positive ones ("unpaid" contains "paid",
     * "inactive" contains "active", "partially paid" contains "paid").
     */
    public static function statusTone($value): string
    {
        $status = Str::of((string) $value)->lower()->replaceMatches('/[_\-]+/', ' ')->squish()->value();

        if ($status === '') {
            return 'neutral';
        }

        $tones = [
            'danger' => [
                'not ', 'unpaid', 'un paid', 'fail', 'cancel', 'reject', 'declin', 'inactive', 'in active',
                'overdue', 'expired', 'suspend', 'deactivat', 'disabled', 'abnormal', 'critical', 'missed',
                'no show', 'blocked', 'out of stock', 'denied', 'error', 'void',
            ],
            'warning' => [
                'pending', 'partial', 'part paid', 'in progress', 'processing', 'awaiting', 'on hold', 'hold',
                'draft', 'scheduled', 'queue', 'waiting', 'review', 'refund', 'due', 'low stock', 'requested',
                'unverified', 'incomplete', 'trial',
            ],
            'success' => [
                'paid', 'complete', 'active', 'approved', 'success', 'discharged', 'released', 'verified',
                'dispensed', 'done', 'resolved', 'normal', 'settled', 'delivered', 'confirmed', 'available',
                'in stock', 'enabled', 'closed', 'attended', 'collected', 'issued',
            ],
            'info' => [
                'admitted', 'ongoing', 'open', 'new', 'checked in', 'check in', 'sent', 'ordered', 'assigned',
                'transferred', 'referred', 'started',
            ],
        ];

        foreach ($tones as $tone => $needles) {
            foreach ($needles as $needle) {
                if (str_starts_with($status, $needle) || str_contains($status, ' ' . trim($needle)) || $status === trim($needle)) {
                    return $tone;
                }
            }
        }

        return 'neutral';
    }

    /**
     * Whether a column holds a status, and so is drawn as a coloured pill.
     */
    public static function isStatusColumn($column): bool
    {
        $column = Str::of((string) $column)->lower()->replaceMatches('/[_\-]+/', ' ')->squish()->value();

        return $column === 'status' || $column === 'state' || str_contains($column, 'status');
    }

    /**
     * A column key as it reads in a table heading.
     */
    public static function heading($column): string
    {
        return ucwords(str_replace('_', ' ', (string) $column));
    }

    /**
     * A cell value as it is printed.
     *
     * Missing values print as a dash rather than the word "null", which is
     * what a bare json_encode() of them used to put on the page.
     */
    public static function cell($value): string
    {
        if ($value === null || $value === '' || (is_string($value) && in_array(strtolower(trim($value)), ['null', 'n/a', 'undefined'], true))) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('M j, Y g:i A');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        $value = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : (array) $value;

        if ($value === []) {
            return '—';
        }

        // A flat list of scalars reads better joined than as JSON.
        if (array_is_list($value) && collect($value)->every(fn($item) => is_scalar($item) || $item === null)) {
            return implode(', ', array_filter($value, fn($item) => $item !== null && $item !== ''));
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
    }

    /**
     * The paper a table is printed on, from how many columns it has.
     *
     * Always landscape. A4 holds a dozen-odd short columns comfortably; past
     * that the page grows rather than the type shrinking, so a wide export stays
     * readable when it is zoomed to fit.
     */
    public static function paperFor(int $columns): string
    {
        return match (true) {
            $columns <= 13 => 'A4',
            $columns <= 19 => 'A3',
            $columns <= 27 => 'A2',
            default => 'A1',
        };
    }

    /**
     * The tenant the request is running for, if any.
     */
    protected static function currentTenant(): ?Tenant
    {
        try {
            return Tenant::current();
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function build(?Tenant $tenant): array
    {
        $name = trim((string) ($tenant->name ?? '')) ?: self::FALLBACK_NAME;
        $logo = self::logo($tenant->logo ?? null);

        return [
            'name' => $name,
            'initials' => self::initials($name),
            'address' => trim((string) ($tenant->address ?? '')) ?: null,
            'contact' => collect([$tenant->phone_number ?? null, $tenant->email ?? null])
                ->map(fn($value) => trim((string) $value))
                ->filter()
                ->implode('  |  ') ?: null,
            'logo' => $logo['logo'] ?? null,
            'watermark' => $logo['watermark'] ?? null,
        ];
    }

    /**
     * Up to two initials, for the badge printed when a hospital has no logo.
     */
    protected static function initials(string $name): string
    {
        $words = collect(preg_split('/\s+/', $name) ?: [])
            ->filter(fn($word) => preg_match('/^[A-Za-z0-9]/', $word))
            ->reject(fn($word) => in_array(strtolower($word), ['of', 'and', 'the', '&'], true))
            ->values();

        $initials = $words->take(2)->map(fn($word) => strtoupper(mb_substr($word, 0, 1)))->implode('');

        return $initials ?: 'E';
    }

    /**
     * The logo, embedded as data URIs: as uploaded, and a greyscale copy for
     * the watermark.
     *
     * Embedded rather than linked so Dompdf never has to reach the network
     * itself (remote fetching stays off), and so a logo that cannot be fetched
     * costs the document its logo rather than the export failing.
     *
     * @return array{logo: string, watermark: string}|null
     */
    protected static function logo(?string $source): ?array
    {
        $source = trim((string) $source);

        if ($source === '') {
            return null;
        }

        $build = function () use ($source) {
            return self::embed($source) ?? [];
        };

        try {
            $logo = Cache::remember(
                'pdf-branding:logo:' . md5($source),
                now()->addHours(self::LOGO_CACHE_HOURS),
                $build
            );
        } catch (\Throwable $th) {
            // A cache store that cannot hold the bytes must not cost the
            // document its logo.
            $logo = $build();
        }

        return empty($logo) ? null : $logo;
    }

    /**
     * @return array{logo: string, watermark: string}|null
     */
    protected static function embed(string $source): ?array
    {
        try {
            $bytes = self::read($source);

            if ($bytes === null || $bytes === '') {
                return null;
            }

            $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;

            if (!$image) {
                // Not something GD can open (an SVG, most likely). Dompdf draws
                // SVG itself, so it is passed through; it just goes without the
                // greyscale treatment.
                $mime = str_contains(substr($bytes, 0, 512), '<svg') ? 'image/svg+xml' : null;

                if (!$mime) {
                    return null;
                }

                $uri = 'data:' . $mime . ';base64,' . base64_encode($bytes);

                return ['logo' => $uri, 'watermark' => $uri];
            }

            $image = self::fit($image);
            $logo = self::png($image);

            imagefilter($image, IMG_FILTER_GRAYSCALE);
            $watermark = self::png($image);

            imagedestroy($image);

            return [
                'logo' => 'data:image/png;base64,' . base64_encode($logo),
                'watermark' => 'data:image/png;base64,' . base64_encode($watermark),
            ];
        } catch (\Throwable $th) {
            report($th);

            return null;
        }
    }

    /**
     * The logo's bytes, from wherever it lives: a URL (Cloudinary, in
     * practice), a data URI, or a path on one of the app's disks.
     */
    protected static function read(string $source): ?string
    {
        if (str_starts_with($source, 'data:')) {
            $payload = FileUploadHelper::decodeBase64File($source);

            return $payload['data'] ?? null;
        }

        if (preg_match('#^https?://#i', $source)) {
            $response = Http::timeout(6)->connectTimeout(4)->get($source);

            return $response->successful() ? $response->body() : null;
        }

        $path = ltrim($source, '/');

        foreach ([public_path($path), storage_path('app/public/' . preg_replace('#^storage/#', '', $path))] as $candidate) {
            if (is_file($candidate)) {
                return file_get_contents($candidate) ?: null;
            }
        }

        $disk = Storage::disk(config('filesystems.default'));

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    /**
     * Scale an image down to the embed size, keeping its transparency.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    protected static function fit($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::LOGO_MAX_EDGE / max($width, $height, 1));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        imagedestroy($image);

        return $canvas;
    }

    /**
     * @param  \GdImage  $image
     */
    protected static function png($image): string
    {
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}
