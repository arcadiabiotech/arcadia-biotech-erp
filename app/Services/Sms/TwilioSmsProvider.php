<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio (https://www.twilio.com) SMS. Configure via TWILIO_SID /
 * TWILIO_AUTH_TOKEN / TWILIO_FROM_NUMBER in .env — see config/sms.php.
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    public function send(string $mobile, string $message): bool
    {
        $config = config('sms.providers.twilio');

        if (empty($config['sid']) || empty($config['auth_token']) || empty($config['from'])) {
            Log::warning('Twilio SMS provider is active but TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM_NUMBER are not fully set.');

            return false;
        }

        $response = Http::withBasicAuth($config['sid'], $config['auth_token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['sid']}/Messages.json", [
                'To' => $mobile,
                'From' => $config['from'],
                'Body' => $message,
            ]);

        return $response->successful();
    }
}
