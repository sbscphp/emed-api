{{--
    What repeats on every page: the faded hospital watermark behind the content
    and the running footer.

    Both are fixed-position, which Dompdf repeats on each page, and must come
    first in <body> for it to do so from page one. The page's bottom margin has
    to leave room for the footer, which sits just below the content area.

    Expects `$branding`. `$watermarkSize` sets the size of the hospital name
    under the watermark logo, so it can grow with the paper, and
    `$orientation` (landscape by default) sizes and places the watermark.

    Dompdf cannot centre a box vertically, so the seal's width (a share of the
    page width) and its offset from the top (a share of the page height) are
    worked out together for each orientation: every A-series sheet has the
    same proportions, so one pair keeps the seal and the name under it in the
    middle of the page from A4 to A1, clear of the header.
--}}
@php
    $portrait = ($orientation ?? 'landscape') === 'portrait';

    [$sealWidth, $watermarkTop] = empty($branding['watermark'])
        ? [null, '47%']
        : ($portrait ? ['44%', '33%'] : ['26%', '27%']);
@endphp
<div class="pdf-watermark" style="top: {{ $watermarkTop }};">
    @if (!empty($branding['watermark']))
        <img class="pdf-watermark__logo" src="{{ $branding['watermark'] }}" style="width: {{ $sealWidth }};" alt="">
    @endif
    <div class="pdf-watermark__name" style="font-size: {{ $watermarkSize ?? 26 }}px;">
        {{ $branding['name'] }}
    </div>
</div>

<div class="pdf-footer">
    <table>
        <tr>
            <td style="text-align: left;">Generated {{ now()->format('M j, Y g:i A') }}</td>
            <td style="text-align: center;">{{ $footerNote ?? 'EMED EMR' }}</td>
            <td style="text-align: right;"><span class="pdf-footer__page"></span></td>
        </tr>
    </table>
</div>
