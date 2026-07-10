<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Corporate email domain
    |--------------------------------------------------------------------------
    |
    | Access to COSTFLOW is restricted to this domain. A user may type just the
    | local part ("alfi") on the sign-in form and the suffix is appended.
    |
    */
    'email_domain' => env('COSTFLOW_EMAIL_DOMAIN', 'bpe.com.my'),

    /*
    |--------------------------------------------------------------------------
    | Sign-in lockout
    |--------------------------------------------------------------------------
    */
    'max_login_attempts' => (int) env('COSTFLOW_MAX_LOGIN_ATTEMPTS', 3),
    'lock_minutes' => (int) env('COSTFLOW_LOCK_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Idle timeout (minutes)
    |--------------------------------------------------------------------------
    |
    | Enforced server-side by the EnforceIdleTimeout middleware, and mirrored
    | client-side by a watchdog that redirects to the sign-in page.
    |
    */
    'idle_minutes' => (int) env('COSTFLOW_IDLE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | One-time passcode sign-in
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'ttl_minutes' => (int) env('COSTFLOW_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('COSTFLOW_OTP_MAX_ATTEMPTS', 5),
        'length' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow notification emails
    |--------------------------------------------------------------------------
    |
    | When true, Management is emailed on submit and the owning engineer is
    | emailed on approve/return.
    |
    */
    'notify_workflow' => (bool) env('COSTFLOW_NOTIFY_WORKFLOW', true),

    /*
    |--------------------------------------------------------------------------
    | Company details shown on the WCC / BPE Price documents
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => 'BPE ENERGY SDN. BHD.',
        'address' => 'No. 21-1, Jalan Medan Bukit Indah 4, Bukit Indah, 68000 Ampang, Selangor.',
        'tel' => '+603-4296 5245',
        'email' => 'info@bpe.com.my',
        'sst' => 'W10-1904-32100039',
    ],

    'version' => '2.1',

    /*
    |--------------------------------------------------------------------------
    | Reference data
    |--------------------------------------------------------------------------
    */
    'departments' => [
        'Electrical',
        'Instrument',
        'CCVT',
        'NWK 99',
        'UPS & Battery',
        'Subsea Cable',
        'Diesel Generator',
    ],

    'statuses' => ['Draft', 'Costed', 'Submitted', 'Approved', 'Returned'],

    'roles' => ['engineer', 'management', 'it'],
];
