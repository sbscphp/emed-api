{{--
    The house style every exported PDF shares: the branded header, the
    watermark, the running footer, the data table and the status pills.

    Every selector is prefixed `pdf-` so a document with styles of its own (the
    patient app's reports and receipts) can include this without either set
    overriding the other. Dompdf supports neither flexbox nor grid, so the
    layout is tables throughout.
--}}
@php
    $primary = \App\Helpers\PdfBranding::PRIMARY;
    $primaryDark = \App\Helpers\PdfBranding::PRIMARY_DARK;
    $primarySoft = \App\Helpers\PdfBranding::PRIMARY_SOFT;
@endphp
<style>
    .pdf-header,
    .pdf-footer,
    .pdf-watermark,
    .pdf-table,
    .pdf-empty {
        font-family: "DejaVu Sans", sans-serif;
    }

    /* Header ------------------------------------------------------------ */

    table.pdf-header {
        width: 100%;
        border-collapse: collapse;
    }

    /* Its own box rather than the table's border, which Dompdf draws per
       cell and so leaves a seam between them. */
    .pdf-rule {
        height: 3px;
        background: {{ $primary }};
        margin-bottom: 16px;
    }

    .pdf-header td {
        vertical-align: bottom;
        padding: 0 0 10px;
    }

    .pdf-header__brand { text-align: left; }
    .pdf-header__doc { text-align: right; }

    .pdf-header__logo {
        height: 48px;
        margin-bottom: 8px;
    }

    .pdf-header__monogram {
        width: 46px;
        height: 18px;
        padding: 14px 0;
        line-height: 18px;
        border-radius: 23px;
        background: {{ $primary }};
        color: #ffffff;
        font-size: 16px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 8px;
    }

    .pdf-header__name {
        font-size: 15px;
        font-weight: bold;
        color: {{ $primaryDark }};
    }

    .pdf-header__meta {
        font-size: 9px;
        color: #8a90a2;
        margin-top: 3px;
    }

    .pdf-header__stamp {
        display: inline-block;
        border: 2px solid #067647;
        color: #067647;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 1.5px;
        padding: 4px 12px;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .pdf-header__title {
        font-size: 22px;
        font-weight: bold;
        color: {{ $primaryDark }};
    }

    /* Watermark --------------------------------------------------------- */

    /* Painted first: Dompdf draws positioned boxes over the page unless
       they are given a negative z-index. It cannot centre a box vertically,
       so pdf-chrome sets `top` from the page's orientation. */
    .pdf-watermark {
        position: fixed;
        z-index: -10;
        left: 0;
        right: 0;
        text-align: center;
    }

    .pdf-watermark__logo {
        width: 40%;
        opacity: 0.07;
    }

    .pdf-watermark__name {
        color: #000000;
        font-weight: bold;
        letter-spacing: 1px;
        opacity: 0.05;
        margin-top: 14px;
    }

    /* Footer ------------------------------------------------------------ */

    .pdf-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -34px;
        height: 22px;
        border-top: 1px solid #e6e3f1;
        padding-top: 6px;
    }

    .pdf-footer table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-footer td {
        width: 33.33%;
        font-size: 8px;
        color: #8a90a2;
    }

    .pdf-footer__page:after {
        content: "Page " counter(page);
    }

    /* Data table -------------------------------------------------------- */

    table.pdf-table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-table thead {
        display: table-header-group;
    }

    .pdf-table tr {
        page-break-inside: avoid;
    }

    .pdf-table th {
        background: {{ $primary }};
        color: #ffffff;
        font-size: 8.5px;
        font-weight: bold;
        text-align: left;
        vertical-align: middle;
        padding: 9px 7px;
    }

    .pdf-table td {
        font-size: 8.5px;
        color: #1f2430;
        vertical-align: middle;
        padding: 9px 7px;
        border-bottom: 1px solid #ece9f6;
        word-wrap: break-word;
    }

    .pdf-table td.pdf-table__key {
        font-weight: bold;
        color: #111827;
    }

    .pdf-table tfoot td {
        background: {{ $primarySoft }};
        color: {{ $primaryDark }};
        font-weight: bold;
        border-top: 2px solid {{ $primary }};
        border-bottom: 0;
    }

    .pdf-table .pdf-num {
        text-align: right;
    }

    .pdf-empty {
        padding: 40px 0;
        text-align: center;
        color: #8a90a2;
        font-size: 11px;
    }

    /* Status pills ------------------------------------------------------ */

    .pdf-pill {
        display: inline-block;
        padding: 3px 7px;
        border-radius: 7px;
        font-size: 7.5px;
        font-weight: bold;
        line-height: 1.25;
    }

    .pdf-pill--success { background: #e3f6ec; color: #067647; }
    .pdf-pill--danger { background: #fde4e7; color: #b42318; }
    .pdf-pill--warning { background: #fff1db; color: #b54708; }
    .pdf-pill--info { background: #ece7ff; color: #5534d6; }
    .pdf-pill--neutral { background: #eef0f4; color: #475467; }
</style>
