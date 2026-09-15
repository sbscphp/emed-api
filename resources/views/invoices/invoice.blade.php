<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $billing->invoice_number }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; color: #333333; font-size: 12px; }
        .header { width: 100%; border-bottom: 3px solid #234E44; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .brand-name { font-size: 18px; font-weight: bold; color: #234E44; }
        .brand-sub { font-size: 11px; color: #777777; }
        .doc-title { font-size: 22px; font-weight: bold; color: #234E44; text-align: right; }
        .meta { font-size: 11px; text-align: right; color: #555555; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; color: #ffffff; }
        .badge-paid { background: #234E44; }
        .badge-part { background: #C9A227; }
        .badge-pending { background: #9aa3b2; }
        .section { margin-bottom: 16px; }
        .section h3 { font-size: 12px; color: #234E44; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .5px; }
        .box { background: #f0f4f2; padding: 10px 12px; border-radius: 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #234E44; color: #ffffff; text-align: left; padding: 8px; font-size: 11px; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        table.items td.num, table.items th.num { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals td.label { color: #555555; }
        .totals td.val { text-align: right; }
        .totals tr.grand td { font-size: 14px; font-weight: bold; color: #234E44; border-top: 2px solid #234E44; }
        .totals tr.out td { color: #b00020; font-weight: bold; }
        .notes { margin-top: 18px; font-size: 11px; color: #555555; }
        .footer { margin-top: 26px; text-align: center; font-size: 10px; color: #9aa3b2; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>

<body>
    @php
        $money = fn ($v) => '₦' . number_format((float) $v, 2);
        $status = $billing->payment_status;
        $badgeClass = $status === 'Paid' ? 'badge-paid' : ($status === 'Part Paid' ? 'badge-part' : 'badge-pending');
        $logo = public_path('assets/img/enugu-logo.png');
    @endphp

    <table class="header">
        <tr>
            <td style="width:60%;">
                @if (file_exists($logo))
                    <img src="{{ $logo }}" alt="Enugu International Hospital" width="70" style="margin-bottom:6px;">
                @endif
                <div class="brand-name">Enugu International Hospital</div>
                <div class="brand-sub">Enugu State, Nigeria</div>
            </td>
            <td style="width:40%;">
                <div class="doc-title">INVOICE</div>
                <div class="meta">
                    <strong>{{ $billing->invoice_number }}</strong><br>
                    Date: {{ \Illuminate\Support\Carbon::parse($billing->billing_date)->format('d M, Y') }}<br>
                    <span class="badge {{ $badgeClass }}">{{ strtoupper($status ?? 'Pending') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h3>Billed To</h3>
        <div class="box">
            <strong>{{ $billing->patient_name ?? optional($billing->patient)->firstname . ' ' . optional($billing->patient)->lastname }}</strong><br>
            @if (optional($billing->patient)->patientno) Patient No: {{ $billing->patient->patientno }}<br> @endif
            @if (optional($billing->patient)->phoneno) {{ $billing->patient->phoneno }}<br> @endif
            @if (optional($billing->patient)->email) {{ $billing->patient->email }} @endif
        </div>
    </div>

    <div class="section">
        <h3>Items</h3>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:45%;">Item</th>
                    <th style="width:25%;">Service Unit</th>
                    <th class="num" style="width:10%;">Qty</th>
                    <th class="num" style="width:20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($billing->billingLogDetails as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ optional($item->serviceUnit)->name ?? '—' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ $money($item->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center; color:#9aa3b2;">No items</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="val">{{ $money($billing->total_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Discount</td>
            <td class="val">- {{ $money($billing->discount) }}</td>
        </tr>
        <tr>
            <td class="label">VAT</td>
            <td class="val">{{ $money($billing->tax_amount) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Grand Total</td>
            <td class="val">{{ $money($billing->grand_total) }}</td>
        </tr>
        <tr>
            <td class="label">Amount Paid</td>
            <td class="val">{{ $money($billing->amount_paid) }}</td>
        </tr>
        <tr class="out">
            <td class="label">Outstanding</td>
            <td class="val">{{ $money($billing->amount_outstanding) }}</td>
        </tr>
    </table>

    @if (!empty($billing->notes))
        <div class="notes">
            <strong>Notes:</strong> {{ $billing->notes }}
        </div>
    @endif

    <div class="footer">
        Thank you for choosing Enugu International Hospital.<br>
        &copy; {{ date('Y') }} {{ config('app.name') }}. Enugu State, Nigeria.
    </div>
</body>

</html>
