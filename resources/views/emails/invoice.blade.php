<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width" />
    <title>Your Invoice</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6fa; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#f4f6fa; padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 18px rgba(16,24,40,.08);">

                    <tr>
                        <td align="center" style="background:#f0f4f2; padding:24px 0;">
                            <img src="{{ asset('assets/img/enugu-logo.png') }}" alt="Enugu International Hospital"
                                width="110" style="display:block;">
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:22px 30px 4px;">
                            <h2 style="margin:0; color:#234E44; font-size:20px; font-weight:700;">Invoice {{ $billing->invoice_number }}</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 40px 26px; color:#374151; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 12px;">Hello {{ $billing->patient_name }},</p>

                            <p style="margin:0 0 12px;">
                                Please find your invoice <strong>{{ $billing->invoice_number }}</strong> attached as a PDF.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin:8px 0 14px; font-size:14px; color:#374151;">
                                <tr>
                                    <td style="padding:4px 0;">Grand Total</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:700; color:#234E44;">
                                        &#8358;{{ number_format((float) $billing->grand_total, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;">Amount Paid</td>
                                    <td style="padding:4px 0; text-align:right;">&#8358;{{ number_format((float) $billing->amount_paid, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;">Outstanding</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:700;">&#8358;{{ number_format((float) $billing->amount_outstanding, 2) }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px;">
                                You can also view and pay this invoice from the mobile app.
                            </p>

                            <p style="margin:18px 0 0; color:#6b7280; font-size:13px;">
                                If you have any questions about this invoice, please contact our billing desk.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="background:#f8fafc; padding:18px 24px; color:#9aa3b2; font-size:13px;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. Enugu State, Nigeria.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
