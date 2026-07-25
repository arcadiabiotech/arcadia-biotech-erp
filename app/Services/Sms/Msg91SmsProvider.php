<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MSG91 (https://msg91.com) transactional SMS. Configure via
 * MSG91_AUTH_KEY / MSG91_ROUTE in .env — see config/sms.php.
 */
class Msg91SmsProvider implements SmsProviderInterface
{
    public function send(string $mobile, string $message): bool
    {
        $config = config('sms.providers.msg91');

        if (empty($config['auth_key'])) {
            Log::warning('MSG91 SMS provider is active but MSG91_AUTH_KEY is not set.');

            return false;
        }

        $response = Http::asForm()->post('https://api.msg91.com/api/sendhttp.php', [
            'authkey' => $config['auth_key'],
            'mobiles' => $this->normalize($mobile),
            'message' => $message,
            'sender' => config('sms.from'),
            'route' => $config['route'] ?? '4',
        ]);

        return $response->successful();
    }

    private function normalize(string $mobile): string
    {
        return '91'.preg_replace('/\D/', '', $mobile);
    }
}
