<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, sans-serif;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">

                <!-- Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">

                    <!-- Header Image -->
                    <tr>
                        <td align="center">
                            <img src="https://via.placeholder.com/600x150.png?text=Header+Image" 
                                 alt="Header" width="600" style="display:block; max-width:100%;">
                        </td>
                    </tr>

                    <!-- EMED Title -->
                    <tr>
                        <td align="center" style="padding: 15px 20px 0px; font-size:22px; font-weight:bold; color:#6c3ee7;">
                            EMED
                        </td>
                    </tr>

                    <!-- Heading -->
                    <tr>
                        <td align="center" style="padding: 10px 20px 20px; font-size:20px; font-weight:bold; color:#6c3ee7;">
                            Verify Your Email to Access Your Account
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td align="left" style="padding: 0 30px 20px; color:#555555; font-size:14px; line-height:22px;">
                            <p>Hello {{ $data['firstname'] }},</p>

                            <p>Thank you for signing up with EMED. To complete your registration and log in, please verify your email address by clicking the button below:</p>

                            <p>If the button doesn’t work, copy and paste this link into your browser:<br>
                                <a href="{{ $verificationUrl }}" style="color:#6c3ee7;">{{ $verificationUrl }}</a>
                            </p>

                            <p>For security reasons, this link will expire in 24 hours. If you didn’t create an account, you can safely ignore this email.</p>

                            <p>Best regards,<br>
                                The EMR Team</p>
                        </td>
                    </tr>

                    <!-- Button -->
                    <tr>
                        <td align="center" style="padding: 10px 30px 40px;">
                            <a href="{{ $verificationUrl }}" 
                               style="background-color:#9d6efc; color:#ffffff; text-decoration:none; 
                                      padding:12px 40px; border-radius:6px; display:inline-block; 
                                      font-size:16px; font-weight:bold;">
                                Verify Email
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 20px 30px; font-size:12px; color:#999999; line-height:18px; border-top:1px solid #eee;">
                            This email was sent to <a href="mailto:superadmin@emed.com" style="color:#6c3ee7; text-decoration:none;">superadmin@emed.com</a>. 
                            If you'd rather not receive this kind of email, you can unsubscribe or manage your email preferences.<br><br>
                            © 2024 EMED, Lagos State, Nigeria.
                            <br><br>
                            <!-- Social icons -->
                            <a href="#" style="margin:0 5px;"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png" width="20"></a>
                            <a href="#" style="margin:0 5px;"><img src="https://cdn-icons-png.flaticon.com/512/733/733547.png" width="20"></a>
                            <a href="#" style="margin:0 5px;"><img src="https://cdn-icons-png.flaticon.com/512/733/733558.png" width="20"></a>
                        </td>
                    </tr>

                </table>
                <!-- End Container -->

            </td>
        </tr>
    </table>

</body>
</html>
