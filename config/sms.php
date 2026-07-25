<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | Which driver App\Services\Sms\SmsManager resolves to an
    | App\Contracts\SmsProviderInterface implementation. "log" is the safe
    | default for local/dev — it writes the message (and OTP) to the log
    | instead of calling a real gateway, exactly like MAIL_MAILER=log does
    | for mail in this project. Switch via SMS_PROVIDER in .env; no code
    | change needed to go live with a real provider later.
    |
    | Supported: "log", "msg91", "fast2sms", "twilio", "sns"
    |
    */

    'default' => env('SMS_PROVIDER', 'log'),

    'from' => env('SMS_SENDER_ID', 'ARCADIA'),

    'providers' => [

        'log' => [
            // No credentials needed — writes to the configured log channel.
        ],

        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
            'route' => env('MSG91_ROUTE', '4'),
        ],

        'fast2sms' => [
            'api_key' => env('FAST2SMS_API_KEY'),
            'sender_id' => env('FAST2SMS_SENDER_ID'),
        ],

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM_NUMBER'),
        ],

        'sns' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],

    ],

];
