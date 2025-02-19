<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>:: Password Reset ::</title>
    <link rel="stylesheet" href="{{asset('assets')}}/css/password-reset.css">
</head>

<body>
    <div class="email-container">
        <div class="email-content">
            <h1 class="header-text">Reset Password</h1>
            <p>Hello {{$data['name']}},</p>
            <p>You requested to change the password on your account.</p>

            <p><strong>Email:</strong> {{$data['email']}}</p>

            <p>Please click on the button below to continue to reset your password. Also, note that this link expires in
                10 minutes.</p>

            <p>If you didn’t initiate this reset password attempt, please contact our support team immediately via our email or
                chat at <a href="mailto:support@emed.com">support@emed.com</a></p>
            <a href="{{env('APP_URL')}}/auth/create-new-password/{{$data['verification_code']}}?email={{$data['email']}}" class="btn-primary">Reset Password</a>
        </div>


        <div class="email-footer">
            <p>&copy; <span id="year"></span> {{env("APP_NAME")}}, Lagos State, Nigeria.</p>
            <div class="social-icons">
                <a href="#"><img src="{{asset('assets')}}/img/twitter.png" alt="Twitter"></a>
                <a href="#"><img src="{{asset('assets')}}/img/facebook.png" alt="Facebook"></a>
                <a href="#"><img src="{{asset('assets')}}/img/instagram.png" alt="Instagram"></a>
            </div>
        </div>
    </div>
    <script>
        document.getElementById("year").textContent = new Date().getFullYear();
    </script>
</body>

</html>