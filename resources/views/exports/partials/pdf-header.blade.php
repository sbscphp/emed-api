{{--
    The branded document header: the hospital's logo and name on the left, the
    document's title and date on the right, over a rule in EMED purple.

    Expects `$branding` (shared by the export view composer) and `$title`;
    `$subtitle` replaces the "hospital · date" line, and `$stamp` prints a
    stamp (a receipt's "Paid") above the title.
--}}
<table class="pdf-header">
    <tr>
        <td class="pdf-header__brand">
            @if (!empty($branding['logo']))
                <img class="pdf-header__logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">
            @else
                <div class="pdf-header__monogram">{{ $branding['initials'] }}</div>
            @endif
            <div class="pdf-header__name">{{ $branding['name'] }}</div>
            @if (!empty($branding['address']))
                <div class="pdf-header__meta">{{ $branding['address'] }}</div>
            @endif
            @if (!empty($branding['contact']))
                <div class="pdf-header__meta">{{ $branding['contact'] }}</div>
            @endif
        </td>
        <td class="pdf-header__doc">
            @if (!empty($stamp))
                <div class="pdf-header__stamp">{{ $stamp }}</div>
            @endif
            <div class="pdf-header__title">{{ $title }}</div>
            <div class="pdf-header__meta">
                {{ $subtitle ?? ($branding['name'] . ' · ' . now()->format('M j, Y')) }}
            </div>
        </td>
    </tr>
</table>
<div class="pdf-rule"></div>
