{{--
    The layout every exported list and report is printed in: the hospital's
    branded header, its watermark and the running footer around whatever the
    page puts in `content`.

    The paper is set here, through `@page`, rather than by the caller: Dompdf
    takes a CSS page size over the one passed to setPaper(), which lets a view
    size the page to what it holds. A view sets `$paper` (A4 to A1) and, if it
    must, `$orientation`; both default to A4 landscape.

    `$branding` is shared by the export view composer (AppServiceProvider).
--}}
@php
    $paper = strtoupper($paper ?? 'A4');
    $orientation = $orientation ?? 'landscape';
    $watermarkSize = ['A4' => 26, 'A3' => 36, 'A2' => 50, 'A1' => 70][$paper] ?? 26;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $branding['name'] }}</title>
    <style>
        @page {
            size: {{ $paper }} {{ $orientation }};
            margin: 28px 30px 56px;
        }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #1f2430;
            background: #ffffff;
        }
    </style>
    @include('exports.partials.pdf-styles')
    @stack('styles')
</head>

<body>
    @include('exports.partials.pdf-chrome', ['watermarkSize' => $watermarkSize])

    @include('exports.partials.pdf-header')

    @yield('content')
</body>

</html>
