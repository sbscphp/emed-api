<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $hospitalName }}</title>
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
                                alt="Hospital Logo" width="80" style="margin: 20px 0;">
                        </td>
                    </tr>

                    <!-- Hospital Name -->
                    <tr>
                        <td align="center" style="padding-top: 10px; font-size:22px; font-weight:700; color:#6C4BF4;">
                            {{ strtoupper($hospitalName) }}
                        </td>
                    </tr>

                    <!-- Main Heading -->
                    <tr>
                        <td align="center"
                            style="padding: 10px 25px 20px; font-size:18px; font-weight:600; color:#6C4BF4;">
                            Welcome to {{ $hospitalName }}
                        </td>
                    </tr>

                    <!-- Email Body -->
                    <tr>
                        <td align="left"
                            style="padding: 0 40px 10px; color:#333333; font-size:15px; line-height:24px;">
                            <p>Hello {{ $name }},</p>

                            <p>Your patient record has been created at <strong>{{ $hospitalName }}</strong>, and an
                                <strong>{{ $appName }}</strong> account has been set up for you.
                            </p>

                            <p>With the {{ $appName }} app you can view your appointments, results, prescriptions and
                                bills from your phone, at any time.</p>
                        </td>
                    </tr>

                    <!-- Credentials -->
                    <tr>
                        <td align="left" style="padding: 0 40px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
                                style="background-color:#f7f5ff; border:1px solid #e5deff; border-radius:8px;">
                                <tr>
                                    <td style="padding: 18px 20px; color:#333333; font-size:15px; line-height:26px;">
                                        <strong style="color:#6C4BF4;">Your login details</strong><br>
                                        @if ($patientNo)
                                            <strong>Hospital Number:</strong> {{ $patientNo }}<br>
                                        @endif
                                        <strong>Hospital:</strong> {{ $hospitalName }}<br>
                                        <strong>Email Address:</strong> {{ $email }}<br>
                                        @if ($password)
                                            <strong>Temporary Password:</strong>
                                            <span
                                                style="font-family: monospace; font-size:16px;">{{ $password }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($password)
                        <tr>
                            <td align="left"
                                style="padding: 16px 40px 0; color:#B23B3B; font-size:14px; line-height:22px;">
                                <strong>Please change this password the first time you sign in.</strong> It is a
                                temporary password, and it should never be shared with anyone.
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td align="left"
                                style="padding: 16px 40px 0; color:#333333; font-size:14px; line-height:22px;">
                                You already have an {{ $appName }} account, so keep using the password you set. Select
                                <strong>{{ $hospitalName }}</strong> when you sign in to reach your records here.
                            </td>
                        </tr>
                    @endif

                    <!-- Store links -->
                    <tr>
                        <td align="center" style="padding: 28px 40px 8px; color:#333333; font-size:15px;">
                            Download the {{ $appName }} app to get started
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 0 40px 10px;">
                            <a href="{{ $androidUrl }}" target="_blank" style="margin: 0 6px; display:inline-block;">
                                <img src="{{ $androidBadge }}" alt="Get it on Google Play" height="44"
                                    style="height:44px; border:0;">
                            </a>
                            <a href="{{ $iosUrl }}" target="_blank" style="margin: 0 6px; display:inline-block;">
                                <img src="{{ $iosBadge }}" alt="Download on the App Store" height="44"
                                    style="height:44px; border:0;">
                            </a>
                        </td>
                    </tr>

                    <!-- Text buttons: mail clients block remote images by default,
                                             so the store links have to survive without the badges. -->
                    <tr>
                        <td align="center" style="padding: 6px 40px 30px;">
                            <a href="{{ $androidUrl }}" target="_blank"
                                style="display:inline-block; margin:6px 4px; padding:12px 22px; background-color:#6C4BF4; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:6px;">
                                Get it on Google Play
                            </a>
                            <a href="{{ $iosUrl }}" target="_blank"
                                style="display:inline-block; margin:6px 4px; padding:12px 22px; background-color:#111111; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:6px;">
                                Download on the App Store
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="left"
                            style="padding: 0 40px 25px; color:#333333; font-size:15px; line-height:24px;">
                            <p style="margin:0;">Best regards,<br>
                                <strong>{{ $hospitalName }} Team</strong>
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
                            If you were not expecting it, please contact
                            <a href="mailto:{{ $supportEmail }}"
                                style="color:#6C4BF4; text-decoration:none;">{{ $supportEmail }}</a>.<br><br>
                            &copy; {{ date('Y') }} {{ $hospitalName }}.
                        </td>
                    </tr>

                </table>
                <!-- End Container -->

            </td>
        </tr>
    </table>

</body>

</html>
