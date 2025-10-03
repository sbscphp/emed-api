<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width"/>
    <title>Reset Password</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6fa; font-family: Arial, Helvetica, sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fa; padding:30px 0;">
    <tr>
        <td align="center">

            <!-- Main card -->
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 18px rgba(16,24,40,.08);">

                <!-- Top image / logo -->
                <tr>
                    <td align="center" style="background:#ffffff; padding-top:26px; padding-bottom:8px;">
                        <!-- Replace this with the banner or logo you want -->
                        <img src="{{ asset('assets/img/logo-header.png') }}" alt="EMED Logo"
                             width="140" style="display:block; border:0; outline:none; text-decoration:none;">
                    </td>
                </tr>

                <!-- Optional decorative image (doctors banner). Uncomment if you have one -->
                {{-- 
                <tr>
                    <td align="center" style="padding:0;">
                        <img src="{{ asset('assets/img/email-header-banner.jpg') }}" alt="" 
                             width="600" style="display:block; width:100%; max-width:600px; height:auto;">
                    </td>
                </tr>
                --}}

                <!-- Title (centered) -->
                <tr>
                    <td align="center" style="padding:26px 30px 8px;">
                        <h2 style="margin:0; color:#6b46ff; font-size:22px; font-weight:700; letter-spacing:0.2px;">
                            Reset Password
                        </h2>
                    </td>
                </tr>

                <!-- Body content -->
                <tr>
                    <td style="padding:0 40px 26px; color:#374151; font-size:15px; line-height:1.6;">
                        <p style="margin:0 0 12px;">Hello <strong>{{ $data['name'] }}</strong>,</p>

                        <p style="margin:0 0 12px;">
                            You requested to change the password on your account.
                        </p>

                        <p style="margin:0 0 12px;"><strong>Email:</strong> {{ $data['email'] }}</p>

                        <p style="margin:0 0 12px;">
                            Please click the button below to continue to reset your password. Also note that this link
                            expires in <strong>1 hour</strong>.
                        </p>

                        <p style="margin:0 0 18px;">
                            If you didn’t initiate this request, please contact our support team immediately at
                            <a href="mailto:support@emed.com" style="color:#6b46ff; text-decoration:none;">support@emed.com</a>.
                        </p>

                        <!-- Centered button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td align="center" style="padding-top:8px; padding-bottom:6px;">
                                    <a href="{{ $data['url'] }}" target="_blank"
                                       style="display:inline-block; padding:14px 34px; background:#6b46ff; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:16px;">
                                        Reset Password
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:18px 0 0; color:#6b7280; font-size:13px;">
                            This email was sent to {{ $data['email'] }}. If you didn't request this, you may safely ignore
                            it or contact support.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="background:#f8fafc; padding:18px 24px; color:#9aa3b2; font-size:13px;">
                        <div style="max-width:520px;">
                            <p style="margin:8px 0 6px;">© {{ date('Y') }} {{ env('APP_NAME') }}. Lagos State, Nigeria.</p>

                            <!-- social icons (optional) -->
                            <p style="margin:6px 0 0;">
                                <a href="#" style="display:inline-block; margin:0 6px;"><img src="{{ asset('assets/img/twitter.png') }}" width="20" alt="Twitter" style="display:block;border:0;"></a>
                                <a href="#" style="display:inline-block; margin:0 6px;"><img src="{{ asset('assets/img/facebook.png') }}" width="20" alt="Facebook" style="display:block;border:0;"></a>
                                <a href="#" style="display:inline-block; margin:0 6px;"><img src="{{ asset('assets/img/instagram.png') }}" width="20" alt="Instagram" style="display:block;border:0;"></a>
                            </p>
                        </div>
                    </td>
                </tr>

            </table>
            <!-- End main card -->

        </td>
    </tr>
</table>

</body>
</html>
