<?php

namespace App\Contracts;

/**
 * Every SMS provider (log/dev, MSG91, Fast2SMS, Twilio, AWS SNS, ...) is a
 * thin adapter behind this single contract, so OtpService never knows which
 * concrete provider is active — that's resolved once, from config/sms.php
 * (driven by SMS_PROVIDER in .env), by App\Services\Sms\SmsManager.
 */
interface SmsProviderInterface
{
    /**
     * Send a text message to a mobile number. Returns true on successful
     * hand-off to the provider (not proof of delivery — providers are
     * typically fire-and-forget over HTTP).
     */
    public function send(string $mobile, string $message): bool;
}
