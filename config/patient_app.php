<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Patient Mobile App
    |--------------------------------------------------------------------------
    |
    | Store listings linked from the invitation email, plus the support address
    | shown on the "Invitation Not Found" screen.
    |
    */

    'name' => env('PATIENT_APP_NAME', 'E.MED'),

    'stores' => [
        'android' => env('PATIENT_APP_ANDROID_URL', 'https://play.google.com/store/apps/details?id=com.sbsc.emed'),
        'ios'     => env('PATIENT_APP_IOS_URL', 'https://apps.apple.com/app/emed/id0000000000'),
    ],

    'badges' => [
        'android' => env(
            'PATIENT_APP_ANDROID_BADGE',
            'https://res.cloudinary.com/dlcenmo5x/image/upload/v1700000000/store/google-play-badge.png'
        ),
        'ios' => env(
            'PATIENT_APP_IOS_BADGE',
            'https://res.cloudinary.com/dlcenmo5x/image/upload/v1700000000/store/app-store-badge.png'
        ),
    ],

    'support_email' => env('PATIENT_APP_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@emed.com')),

    /*
    | How long the token issued by /patient/auth/verify-invitation stays valid
    | before the patient has to look their invitation up again.
    */
    'verification_token_ttl' => (int) env('PATIENT_APP_VERIFICATION_TTL', 60),

    /*
    | Minutes a password reset OTP stays valid, and minutes the patient then has
    | to type a new password once that OTP is verified. Both are deliberately
    | shorter than the invitation window: an OTP is six digits, so its life is
    | part of what keeps it guessable only in theory.
    */
    'password_reset_otp_ttl' => (int) env('PATIENT_APP_RESET_OTP_TTL', 15),
    'password_reset_token_ttl' => (int) env('PATIENT_APP_RESET_TOKEN_TTL', 15),
];
