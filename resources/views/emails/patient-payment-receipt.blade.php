{{--
    Sent the moment a payment is confirmed, to whoever paid — the patient for
    their own bill, the friend who used a support link for theirs.

    The receipt itself rides along as a PDF attachment rather than as a link:
    a link would have to survive the patient changing phones, expire on its own
    terms, and be readable by anyone who forwarded the mail. The attachment is
    theirs the moment it lands, works offline, and needs no route to protect.

    Laid out in tables with inline styles because that is what mail clients
    still render reliably.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment receipt — {{ $hospitalName }}</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family: 'Helvetica Neue', Arial, sans-serif;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 40px 0;">

                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">

                    <!-- Hospital -->
                    <tr>
                        <td align="center" style="background-color:#f1ecff; padding: 22px 20px 18px;">
                            <div style="font-size:20px; font-weight:700; color:#6C4BF4;">
                                {{ strtoupper($hospitalName) }}
                            </div>
                        </td>
                    </tr>

                    <!-- Amount -->
                    <tr>
                        <td align="center" style="padding: 28px 25px 4px;">
                            <div style="font-size:13px; color:#6b7280; letter-spacing:0.4px; text-transform:uppercase;">
                                Payment received
                            </div>
                            <div style="font-size:32px; font-weight:700; color:#067647; padding-top:6px;">
                                {{ $currency }} {{ $amount }}
                            </div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td align="left" style="padding: 18px 40px 6px; color:#333333; font-size:15px; line-height:24px;">
                            <p style="margin:0 0 12px;">Hello {{ $recipientName }},</p>

                            @if ($isSupport)
                                <p style="margin:0 0 12px;">
                                    Thank you for helping settle
                                    <strong>{{ $patientName }}</strong>'s
                                    {{ $billCount > 1 ? 'bills' : 'bill' }} at
                                    <strong>{{ $hospitalName }}</strong>. Your payment has been confirmed and
                                    applied.
                                </p>
                            @else
                                <p style="margin:0 0 12px;">
                                    We have received your payment at <strong>{{ $hospitalName }}</strong>, and it has
                                    been applied to
                                    {{ $billCount > 1 ? $billCount . ' outstanding bills' : 'your bill' }}.
                                </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Payment details -->
                    <tr>
                        <td align="left" style="padding: 6px 40px 0;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
                                style="background-color:#f7f5ff; border:1px solid #e5deff; border-radius:8px;">
                                <tr>
                                    <td style="padding: 18px 20px; color:#333333; font-size:14px; line-height:24px;">
                                        <strong style="color:#6C4BF4;">Payment details</strong><br>
                                        <strong>Reference:</strong> {{ $reference }}<br>
                                        <strong>Date:</strong> {{ $paidAt }}<br>
                                        <strong>Method:</strong> {{ $method }}<br>
                                        <strong>Amount:</strong> {{ $currency }} {{ $amount }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- What it settled -->
                    @if (!empty($bills))
                        <tr>
                            <td align="left" style="padding: 20px 40px 0;">
                                <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.4px; padding-bottom:8px;">
                                    {{ $billCount > 1 ? 'Bills settled' : 'Bill settled' }}
                                </div>

                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
                                    style="border-collapse:collapse; font-size:13.5px; color:#333333;">
                                    @foreach ($bills as $bill)
                                        <tr>
                                            <td style="padding:8px 0; border-bottom:1px solid #eef0f4;">
                                                <strong>{{ $bill['invoice_number'] }}</strong><br>
                                                <span style="color:#6b7280; font-size:12.5px;">{{ $bill['title'] }}</span>
                                            </td>
                                            <td align="right"
                                                style="padding:8px 0; border-bottom:1px solid #eef0f4; white-space:nowrap;">
                                                {{ $currency }} {{ $bill['paid_now'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>

                                @if ($hasOutstanding)
                                    <p style="margin:14px 0 0; font-size:13.5px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:10px 14px;">
                                        {{ $currency }} {{ $outstandingTotal }} is still outstanding on
                                        {{ $billCount > 1 ? 'these bills' : 'this bill' }}. You can settle the balance
                                        from the {{ $appName }} app.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endif

                    <!-- Attachment note -->
                    <tr>
                        <td align="left" style="padding: 22px 40px 0; color:#333333; font-size:15px; line-height:24px;">
                            <p style="margin:0;">
                                Your receipt is attached to this email as a PDF
                                (<strong>{{ $fileName }}</strong>) — open it to view, print or download a copy for your
                                records.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="left" style="padding: 22px 40px 32px; color:#6b7280; font-size:13px; line-height:22px;">
                            <p style="margin:0 0 8px;">
                                Questions about this payment? Reply to this email or contact us at
                                <a href="mailto:{{ $supportEmail }}" style="color:#6C4BF4;">{{ $supportEmail }}</a>.
                            </p>
                            <p style="margin:0; color:#9098a8; font-size:12px;">
                                This is an automated receipt from {{ $appName }} for {{ $hospitalName }}. Please do not
                                share it — it confirms a payment made on your behalf.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
