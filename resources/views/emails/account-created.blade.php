<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>:: Account Created ::</title>
    <link rel="stylesheet" href="{{asset('assets')}}/css/password-reset.css">
</head>

<body>
    <div class="email-container">
        <div class="email-content">
            <h1 class="header-text">Account Created</h1>
            <p>Hello {{$data['fullname']}},</p>
            <p>Your Account Credentials.</p>

            <p><strong>Email:</strong> {{$data['email']}}</p>
            <p><strong>Passwod:</strong> {{$data['password']}}</p>
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