<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fast2SMS (https://www.fast2sms.com) transactional SMS. Configure via
 * FAST2SMS_API_KEY / FAST2SMS_SENDER_ID in .env — see config/sms.php.
 */
class Fast2SmsProvider implements SmsProviderInterface
{
    public function send(string $mobile, string $message): bool
    {
        $config = config('sms.providers.fast2sms');

        if (empty($config['api_key'])) {
            Log::warning('Fast2SMS provider is active but FAST2SMS_API_KEY is not set.');

            return false;
        }

        $response = Http::withHeaders(['authorization' => $config['api_key']])
            ->asForm()
            ->post('https://www.fast2sms.com/dev/bulkV2', [
                'route' => 'q',
                'message' => $message,
                'numbers' => preg_replace('/\D/', '', $mobile),
                'sender_id' => $config['sender_id'] ?? config('sms.from'),
            ]);

        return $response->successful();
    }
}
