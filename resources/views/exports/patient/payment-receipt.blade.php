{{--
    The receipt emailed out the moment a payment is confirmed.

    This is a receipt for a *charge*, not for an invoice — which is what makes it
    work for "pay all" as well as for a single bill. One payment can clear
    several invoices at once, so the body lists what the money was spread across
    and what each of them took, and the itemised lines are only printed when
    there is exactly one bill to itemise.

    The bill-by-bill receipt the app downloads under "Download Receipt" is a
    different document and still lives in receipt.blade.php: that one answers
    "what did this invoice cost and how was it cleared", this one answers "what
    did I just pay for".

    Amounts carry the currency code rather than a symbol: Dompdf lays this out in
    Helvetica, which has no naira glyph, so a symbol comes out as a blank box.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->reference }}</title>
    <style>
        @page { margin: 28px 34px; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2430;
        }

        .header { border-bottom: 2px solid #6d4aff; padding-bottom: 10px; margin-bottom: 16px; }
        .hospital { font-size: 17px; font-weight: bold; color: #2b1b6b; }
        .hospital-address { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .doc-title { font-size: 13px; font-weight: bold; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        .paid-stamp {
            float: right;
            border: 2px solid #067647;
            color: #067647;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1.5px;
            padding: 6px 14px;
            text-transform: uppercase;
        }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 3px 0; vertical-align: top; font-size: 10.5px; }
        .meta .label { color: #6b7280; width: 110px; }
        .meta .value { font-weight: bold; }

        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th {
            background: #f3f0ff;
            color: #2b1b6b;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 7px 8px;
            border-bottom: 1px solid #e2ddf7;
        }
        table.lines td { padding: 7px 8px; border-bottom: 1px solid #eef0f4; font-size: 10.5px; }
        table.lines .num { text-align: right; }
        table.lines tr.total td { font-weight: bold; background: #faf9ff; border-bottom: none; }

        .section-title { font-size: 10px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; margin: 18px 0 4px; }

        table.totals { width: 46%; border-collapse: collapse; margin-top: 12px; float: right; }
        table.totals td { padding: 4px 8px; font-size: 10.5px; }
        table.totals td.label { color: #6b7280; }
        table.totals td.value { text-align: right; font-weight: bold; }
        table.totals tr.grand td { border-top: 1px solid #e2ddf7; padding-top: 7px; font-size: 12px; color: #067647; }

        .clear { clear: both; }

        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9098a8; }
        .empty { padding: 18px; text-align: center; color: #6b7280; font-style: italic; }
    </style>
</head>

<body>
    <div class="header">
        <div class="paid-stamp">Paid</div>
        <div class="hospital">{{ $tenant->name }}</div>
        @if (!empty($tenant->address))
            <div class="hospital-address">{{ $tenant->address }}</div>
        @endif
        @if (!empty($tenant->phone_number) || !empty($tenant->email))
            <div class="hospital-address">
                {{ collect([$tenant->phone_number, $tenant->email])->filter()->implode('  |  ') }}
            </div>
        @endif
        <div class="doc-title">Payment Receipt</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Receipt No.</td>
            <td class="value">{{ $payment->reference }}</td>
            <td class="label">Paid On</td>
            <td class="value">{{ optional($payment->paid_at)->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Patient</td>
            <td class="value">{{ $patientName }}</td>
            <td class="label">Patient ID</td>
            <td class="value">{{ $patient->patientno ?? '-' }}</td>
        </tr>
        <tr>
            {{-- A contribution is receipted to the friend who made it, so the
                 payer is named separately from the patient it was made for. --}}
            <td class="label">Paid By</td>
            <td class="value">{{ $payment->is_support ? $payment->supporter_name : $patientName }}</td>
            <td class="label">Method</td>
            <td class="value">{{ $method }}</td>
        </tr>
        <tr>
            <td class="label">Bills Settled</td>
            <td class="value">{{ $bills->count() }}</td>
            <td class="label">Currency</td>
            <td class="value">{{ $currency }}</td>
        </tr>
    </table>

    <div class="section-title">What this payment went towards</div>

    @if ($bills->isEmpty())
        <div class="empty">This payment is not linked to an invoice.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 22%;">Invoice</th>
                    <th style="width: 30%;">Bill</th>
                    <th style="width: 16%;">Bill Date</th>
                    <th style="width: 16%;" class="num">Paid Now</th>
                    <th style="width: 16%;" class="num">Balance Left</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bills as $bill)
                    <tr>
                        <td>{{ $bill['invoice_number'] }}</td>
                        <td>{{ $bill['title'] }}</td>
                        <td>{{ $bill['billed_at'] ? $bill['billed_at']->format('d M Y') : '-' }}</td>
                        <td class="num">{{ number_format($bill['paid_now'], 2) }}</td>
                        <td class="num">{{ number_format($bill['outstanding'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Total</td>
                    <td class="num">{{ number_format($amount, 2) }}</td>
                    <td class="num">{{ number_format($outstandingTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Itemised only for a single invoice. A "pay all" receipt covering five
         bills would run to pages of lines the payer did not ask for, and the
         per-bill receipt in the app already itemises each one. --}}
    @if ($items->isNotEmpty())
        <div class="section-title">Itemised</div>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 48%;">Item</th>
                    <th style="width: 26%;">Unit</th>
                    <th style="width: 10%;" class="num">Qty</th>
                    <th style="width: 16%;" class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->item_name ?: '-' }}</td>
                        <td>{{ optional($item->serviceUnit)->name ?: '-' }}</td>
                        <td class="num">{{ (int) $item->quantity ?: 1 }}</td>
                        <td class="num">{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals">
        <tr class="grand">
            <td class="label">Amount paid</td>
            <td class="value">{{ $currency }} {{ number_format($amount, 2) }}</td>
        </tr>
        @if ($outstandingTotal > 0)
            <tr>
                <td class="label">Still outstanding</td>
                <td class="value">{{ $currency }} {{ number_format($outstandingTotal, 2) }}</td>
            </tr>
        @endif
    </table>

    <div class="clear"></div>

    <div class="footer">
        Generated {{ now()->format('d M Y, h:i A') }} - {{ $tenant->name }}.
        This receipt confirms payment {{ $payment->reference }} was received in full.
        Please keep it for your records.
    </div>
</body>

</html>
