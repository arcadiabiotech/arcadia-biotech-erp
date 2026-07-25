<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use InvalidArgumentException;

/**
 * Resolves the configured SMS_PROVIDER (config/sms.php) to a concrete
 * App\Contracts\SmsProviderInterface implementation. This is the one place
 * that knows the full list of drivers — everything else in the OTP system
 * (OtpService, controllers) depends only on the interface.
 */
class SmsManager
{
    public function driver(?string $name = null): SmsProviderInterface
    {
        $name ??= config('sms.default', 'log');

        return match ($name) {
            'log' => new LogSmsProvider,
            'msg91' => new Msg91SmsProvider,
            'fast2sms' => new Fast2SmsProvider,
            'twilio' => new TwilioSmsProvider,
            'sns' => new AwsSnsSmsProvider,
            default => throw new InvalidArgumentException("Unknown SMS_PROVIDER [{$name}]. Supported: log, msg91, fast2sms, twilio, sns."),
        };
    }
}
