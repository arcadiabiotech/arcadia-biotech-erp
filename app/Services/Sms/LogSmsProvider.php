<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default/dev driver — writes the message to the log instead of calling a
 * real gateway, the same pattern this project already uses for mail
 * (MAIL_MAILER=log). Lets registration/login OTP flows be fully exercised
 * on a local install with no SMS account configured; switch SMS_PROVIDER
 * in .env to go live with a real provider, no code change required.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $mobile, string $message): bool
    {
        Log::channel(config('logging.default'))->info("[SMS:log-driver] To: {$mobile} | {$message}");

        return true;
    }
}
