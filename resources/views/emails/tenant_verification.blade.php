<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        header {
            text-align: center;
            margin-bottom: 20px;
            background-color: #fff;
            padding: 10px;
            border-radius: 8px;
        }

        header img {
            max-width: 100px;
        }

        h1 {
            color: #333;
        }

        p {
            color: #555;
        }

        .button-container {
            text-align: center;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 20px 0;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            background-color: #4caf50;
            color: #fff;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #45a049;
        }

        .password-pic {
            display: block;
            margin: 20px auto;
            max-width: 100%;
            height: auto;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 12px;
            text-align: center;
        }

        .footer a {
            color: #4caf50;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="u-row" style="margin: 0 auto;max-width: 600px;background-color: #18407c;">
            <div style="display: table;width: 100%;height: 100%;">
                <div class="u-col u-col-100" style="min-width: 600px;display: table-cell;vertical-align: top;">
                    <div style="height: 100%;width: 100%;">
                        <div style="height: 100%; padding: 0px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                                <tbody>
                                    <tr>
                                        <td style="padding:40px 10px 10px;" align="center">
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center">
                                                        <img src="{{ asset('assets/img/logo.jpeg') }}" alt="Logo">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="1em"
                                                            height="1em" viewBox="0 0 24 24">
                                                            <path fill="currentColor"
                                                                d="M12.14 2a10 10 0 1 0 10 10a10 10 0 0 0-10-10m0 18a8 8 0 1 1 8-8a8 8 0 0 1-8 8" />
                                                            <path fill="currentColor"
                                                                d="M16.14 10a3 3 0 0 0-3-3h-5v10h2v-4h1.46l2.67 4h2.4l-2.75-4.12A3 3 0 0 0 16.14 10m-3 1h-3V9h3a1 1 0 0 1 0 2" />
                                                        </svg>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                                <tbody>
                                    <tr>
                                        <td style="padding:10px;" align="center">
                                            <div style="font-size: 14px; color: #e5eaf5; line-height: 140%;">
                                                <p style="font-size: 14px; color: #fff;">
                                                    <strong>EMED</strong>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                                <tbody>
                                    <tr>
                                        <td style="padding:0px 10px 31px;" align="center">
                                            <div style="font-size: 14px; color: #e5eaf5; line-height: 140%;">
                                                <p style="font-size: 28px; color: #fff;">
                                                    <strong>EMAIL VERIFICATION</strong>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h4>Dear {{ $data['firstname'] }},</h4>
        <p>Welcome to EMED!</p>
        <p>We are delighted to have you onboard. Our platform is designed to streamline hospital operations and enhance patient care. To get started, we need to verify your email address to ensure secure communication.</p>
        <p>Please click the link below to verify your email address</p>


        <div class="button-container">
            <a href="{{ $verificationUrl }}" class="button">
                Verify your Email
            </a>
        </div>

        <!-- <img src="{{ asset('assets/img/verify-email.png') }}" style="height: 200px;" alt="Email Verification"
            class="password-pic"> <br> -->

        <p>Once your email is verified, you'll be able to access your account and start exploring our services.</p>
        <p>If you have any questions or need further assistance, feel free to reach out to our customer support team at EMED.</p>
        <p>We're excited to have you on board and we can't wait to share our amazing products with you. Welcome to the EMED family!</p>

        <div class="footer">
            <p>This email was sent from {{ env('APP_NAME') }}. &copy; {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved. |
                <a href="#">Terms and Conditions</a>
            </p>
        </div>
    </div>
</body>

</html>