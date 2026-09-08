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

    /*
    |--------------------------------------------------------------------------
    | Appointment Booking
    |--------------------------------------------------------------------------
    |
    | The house rules the patient booking flow runs on. Hospitals do not publish
    | per doctor rotas yet, so the calendar and the time slots the app offers are
    | generated from these defaults and then thinned out by whatever the chosen
    | doctor is already booked for.
    |
    */

    'appointments' => [

        // How far ahead the booking calendar paints, and the breathing room a
        // hospital needs before an appointment can be booked into.
        'booking_horizon_days' => (int) env('PATIENT_APP_BOOKING_HORIZON_DAYS', 60),
        'minimum_notice_hours' => (int) env('PATIENT_APP_BOOKING_NOTICE_HOURS', 2),

        // Days the clinic runs, as ISO-8601 weekday numbers (1 = Monday).
        'working_days' => [1, 2, 3, 4, 5, 6],

        // The three groups the time slot picker renders under their own heading,
        // sliced into appointments this many minutes long.
        'slot_minutes' => (int) env('PATIENT_APP_SLOT_MINUTES', 30),
        'sessions' => [
            'Morning'   => ['start' => '09:00', 'end' => '12:00'],
            'Afternoon' => ['start' => '13:00', 'end' => '17:00'],
            'Evening'   => ['start' => '17:00', 'end' => '20:00'],
        ],

        // How close to the appointment a patient may still move it or call it
        // off themselves. Past that they are told to ring the hospital.
        'reschedule_notice_hours' => (int) env('PATIENT_APP_RESCHEDULE_NOTICE_HOURS', 24),
        'cancellation_notice_hours' => (int) env('PATIENT_APP_CANCELLATION_NOTICE_HOURS', 6),

        // The window either side of the appointment in which "Confirm Check in"
        // works, and the minutes-per-patient the estimated wait on the check in
        // receipt is worked out with.
        'check_in_opens_minutes_before' => (int) env('PATIENT_APP_CHECK_IN_OPENS_BEFORE', 120),
        'check_in_closes_minutes_after' => (int) env('PATIENT_APP_CHECK_IN_CLOSES_AFTER', 120),
        'average_service_minutes' => (int) env('PATIENT_APP_AVERAGE_SERVICE_MINUTES', 10),

        // Where a tele consultation is held. The name is stored on the
        // appointment as spelled here, so adding one is enough to offer it.
        'meeting_platforms' => [
            ['name' => 'Google Meet', 'slug' => 'google-meet'],
            ['name' => 'Zoom', 'slug' => 'zoom'],
            ['name' => 'Microsoft Teams', 'slug' => 'microsoft-teams'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vitals
    |--------------------------------------------------------------------------
    |
    | The reference ranges the app marks a reading Normal, High or Low against.
    | Adult defaults; a reading with no range here is shown without a verdict
    | rather than guessed at. These are for presentation only — nothing clinical
    | is decided from them.
    |
    */

    'vitals' => [
        'reference_ranges' => [
            'blood_pressure_systolic'  => ['min' => 90, 'max' => 120],
            'blood_pressure_diastolic' => ['min' => 60, 'max' => 80],
            'pulse'                    => ['min' => 60, 'max' => 100],
            'temperature'              => ['min' => 36.1, 'max' => 37.2],
            'blood_sugar'              => ['min' => 70, 'max' => 140],
            'oxygen_saturation'        => ['min' => 95, 'max' => 100],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Records
    |--------------------------------------------------------------------------
    |
    | How recent a record has to be to count towards the "+3 new" badge under
    | each tile of the home screen's health record section.
    |
    */

    'records' => [
        'new_within_days' => (int) env('PATIENT_APP_RECORDS_NEW_WITHIN_DAYS', 30),
    ],
];
