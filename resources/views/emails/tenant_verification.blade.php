<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
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
                        <td align="center" style="background-color:#f5f3ff;">
                            <img src="https://res.cloudinary.com/dlcenmo5x/image/upload/v1763377653/Document/upload_43142_1763377652/kcjk71hu1pmprnko4tls.png"
                                alt="EMED Team" width="600" style="display:block; max-width:100%; height:auto;">
                        </td>
                    </tr>

                    <!-- EMED Text -->
                    <tr>
                        <td align="center" style="padding-top: 10px; font-size:22px; font-weight:700; color:#7F56D9;">
                            EMED
                        </td>
                    </tr>

                    <!-- Main Heading -->
                    <tr>
                        <td align="center"
                            style="padding: 10px 25px 25px; font-size:18px; font-weight:600; color:#7F56D9;">
                            Verify Your Email to Access Your Account
                        </td>
                    </tr>

                    <!-- Email Body -->
                    <tr>
                        <td align="left"
                            style="padding: 0 40px 10px; color:#333333; font-size:15px; line-height:24px;">
                            <p>Hello {{ $name }},</p>

                            <p>Thank you for signing up with EMED. Your OTP verification code is:</p>

                            <!-- OTP -->
                            <p style="font-size: 22px; font-weight:bold; color:#7F56D9; margin: 10px 0;">
                                {{ $token }}
                            </p>

                            <p>For security reasons, this link will expire in 24 hours. If you didn’t create an account,
                                you can safely ignore this email.</p>

                            <p style="margin-top: 25px;">Best regards,<br>
                                The EMR Team
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center"
                            style="padding: 30px 30px; font-size:12px; color:#999999; line-height:18px; border-top:1px solid #eee;">
                            This email was sent to
                            <a href="mailto:{{ $email }}"
                                style="color:#7F56D9; text-decoration:none;">{{ $email }}</a>.
                            If you'd rather not receive this kind of email, you can unsubscribe or manage your email
                            preferences.<br><br>
                            © 2024 EMED, Lagos State, Nigeria.
                            <br><br>

                            <!-- Social icons -->
                            <a href="#" style="margin:0 6px;"><img
                                    src="https://cdn-icons-png.flaticon.com/512/733/733579.png" width="20"
                                    alt="Twitter"></a>
                            <a href="#" style="margin:0 6px;"><img
                                    src="https://cdn-icons-png.flaticon.com/512/733/733547.png" width="20"
                                    alt="Facebook"></a>
                            <a href="#" style="margin:0 6px;"><img
                                    src="https://cdn-icons-png.flaticon.com/512/733/733558.png" width="20"
                                    alt="Instagram"></a>
                        </td>
                    </tr>

                </table>
                <!-- End Container -->

            </td>
        </tr>
    </table>

</body>

</html>
