<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your {{ $appName }} password reset code</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family: 'Helvetica Neue', Arial, sans-serif;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 40px 0;">

                <!-- Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color:#f1ecff;">
                            <img src="https://res.cloudinary.com/dlcenmo5x/image/upload/v1763377653/Document/upload_43142_1763377652/kcjk71hu1pmprnko4tls.png"
                                alt="{{ $appName }}" width="80" style="margin: 20px 0;">
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding-top: 10px; font-size:22px; font-weight:700; color:#6C4BF4;">
                            {{ strtoupper($appName) }}
                        </td>
                    </tr>

                    <tr>
                        <td align="center"
                            style="padding: 10px 25px 20px; font-size:18px; font-weight:600; color:#6C4BF4;">
                            Reset Your Password
                        </td>
                    </tr>

                    <tr>
                        <td align="left"
                            style="padding: 0 40px 10px; color:#333333; font-size:15px; line-height:24px;">
                            <p>Hello {{ $name }},</p>

                            <p>We received a request to reset your {{ $appName }} password. Enter the code below in the
                                app to continue.</p>
                        </td>
                    </tr>

                    <!-- OTP -->
                    <tr>
                        <td align="center" style="padding: 10px 40px 6px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                                style="background-color:#f7f5ff; border:1px solid #e5deff; border-radius:8px;">
                                <tr>
                                    <td align="center"
                                        style="padding: 18px 40px; font-size:32px; font-weight:700; letter-spacing:8px; color:#6C4BF4; font-family: monospace;">
                                        {{ $otp }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 6px 40px 20px; color:#777777; font-size:13px;">
                            This code expires in {{ $expiresInMinutes }} minutes.
                        </td>
                    </tr>

                    <tr>
                        <td align="left"
                            style="padding: 0 40px 25px; color:#333333; font-size:14px; line-height:22px;">
                            <p style="margin:0 0 16px;">If you did not ask to reset your password, you can ignore this
                                email — your password will not change. Never share this code with anyone, including
                                hospital staff.</p>

                            <p style="margin:0;">Best regards,<br>
                                <strong>The {{ $appName }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center"
                            style="padding: 30px; font-size:12px; color:#999999; line-height:18px; border-top:1px solid #eee;">
                            This email was sent to
                            <a href="mailto:{{ $email }}"
                                style="color:#6C4BF4; text-decoration:none;">{{ $email }}</a>.
                            Need help? Contact
                            <a href="mailto:{{ $supportEmail }}"
                                style="color:#6C4BF4; text-decoration:none;">{{ $supportEmail }}</a>.<br><br>
                            &copy; {{ date('Y') }} {{ $appName }}.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
