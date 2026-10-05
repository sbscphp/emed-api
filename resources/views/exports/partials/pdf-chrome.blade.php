{{--
    What repeats on every page: the faded hospital watermark behind the content
    and the running footer.

    Both are fixed-position, which Dompdf repeats on each page, and must come
    first in <body> for it to do so from page one. The page's bottom margin has
    to leave room for the footer, which sits just below the content area.

    Expects `$branding`. `$watermarkSize` sets the size of the hospital name
    under the watermark logo, so it can grow with the paper, and
    `$orientation` (landscape by default) places the watermark in the middle of
    the page: the logo is sized to the page width, so how far down it starts
    depends on how tall the page is relative to that.
--}}
@php
    $watermarkTop = empty($branding['watermark'])
        ? '46%'
        : (($orientation ?? 'landscape') === 'portrait' ? '32%' : '17%');
@endphp
<div class="pdf-watermark" style="top: {{ $watermarkTop }};">
    @if (!empty($branding['watermark']))
        <img class="pdf-watermark__logo" src="{{ $branding['watermark'] }}" alt="">
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
