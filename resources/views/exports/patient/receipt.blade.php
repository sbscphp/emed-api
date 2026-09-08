{{--
    The receipt a patient downloads from the app once a bill is settled.

    Rendered on demand by PatientReportService from the invoice and the payments
    that cleared against it, so it is always the current version — a bill part
    paid by a friend and finished by the patient reads as one receipt with both
    payments on it.

    Amounts are printed with the currency code rather than a symbol: Dompdf lays
    this out in Helvetica, which has no naira glyph, so a symbol would come out
    as a blank box.

    Styling is kept to plain tables and simple CSS because Dompdf supports
    little more than that.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $bill->invoice_number ?? $bill->id }}</title>
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

        .section-title { font-size: 10px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; margin: 18px 0 4px; }

        table.totals { width: 46%; border-collapse: collapse; margin-top: 12px; float: right; }
        table.totals td { padding: 4px 8px; font-size: 10.5px; }
        table.totals td.label { color: #6b7280; }
        table.totals td.value { text-align: right; font-weight: bold; }
        table.totals tr.grand td { border-top: 1px solid #e2ddf7; padding-top: 7px; font-size: 12px; color: #2b1b6b; }
        table.totals tr.balance td { color: #067647; }

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
            <td class="value">{{ $bill->invoice_number ?: ('BILL-' . $bill->id) }}</td>
            <td class="label">Issued</td>
            <td class="value">{{ now()->format('d M Y, h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Patient</td>
            <td class="value">{{ trim(($patient->firstname ?? '') . ' ' . ($patient->lastname ?? '')) ?: '-' }}</td>
            <td class="label">Patient ID</td>
            <td class="value">{{ $patient->patientno ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Bill</td>
            <td class="value">{{ $bill->service_title ?? '-' }}</td>
            <td class="label">Bill Date</td>
            <td class="value">{{ optional($bill->billed_at)->format('d M Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Settled</td>
            <td class="value">
                {{ optional($payments->max('paid_at'))->format('d M Y, h:i A') ?? '-' }}
            </td>
            <td class="label">Currency</td>
            <td class="value">{{ $currency }}</td>
        </tr>
    </table>

    <div class="section-title">What was billed</div>

    @if ($items->isEmpty())
        <div class="empty">This bill carries no itemised lines.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 46%;">Item</th>
                    <th style="width: 26%;">Unit</th>
                    <th style="width: 10%;" class="num">Qty</th>
                    <th style="width: 18%;" class="num">Amount</th>
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
        <tr>
            <td class="label">Total billed</td>
            <td class="value">{{ $currency }} {{ number_format($total, 2) }}</td>
        </tr>
        @if ($discount > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="value">{{ $currency }} {{ number_format($discount, 2) }}</td>
            </tr>
        @endif
        @if ($tax > 0)
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currency }} {{ number_format($tax, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">Amount paid</td>
            <td class="value">{{ $currency }} {{ number_format($paid, 2) }}</td>
        </tr>
        <tr class="balance">
            <td class="label">Balance</td>
            <td class="value">{{ $currency }} {{ number_format($outstanding, 2) }}</td>
        </tr>
    </table>

    <div class="clear"></div>

    <div class="section-title">How it was paid</div>

    @if ($payments->isEmpty())
        {{-- A bill can be settled without a payment row: written off by the
             hospital, or cleared on a flow that predates the app. --}}
        <div class="empty">No payment was recorded against this bill through the app.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 24%;">Date</th>
                    <th style="width: 30%;">Reference</th>
                    <th style="width: 16%;">Channel</th>
                    <th style="width: 16%;">Paid by</th>
                    <th style="width: 14%;" class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ optional($payment->paid_at)->format('d M Y, h:i A') ?? '-' }}</td>
                        <td>{{ $payment->reference ?: '-' }}</td>
                        <td>{{ $payment->channel ?: '-' }}</td>
                        <td>{{ $payment->is_support ? $payment->supporter_name : 'Patient' }}</td>
                        {{-- This bill's share of the charge, not the charge: a
                             "pay all" payment clears several invoices at once,
                             and its full amount would not add up here. --}}
                        <td class="num">{{ number_format((float) ($payment->applied_amount ?? $payment->amount), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated {{ now()->format('d M Y, h:i A') }} - {{ $tenant->name }}.
        This receipt is issued to the patient named above as confirmation of payment.
    </div>
</body>

</html>
