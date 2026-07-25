<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * AWS SNS (https://aws.amazon.com/sns) SMS. Configure via
 * AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION in .env —
 * see config/sms.php.
 *
 * Requires the `aws/aws-sdk-php` Composer package, which is NOT installed
 * in this project (no other AWS service is currently used here, so it
 * wasn't pulled in as a dependency). This driver is a ready-to-activate
 * integration point: run `composer require aws/aws-sdk-php` and it starts
 * working immediately — nothing else in the OTP system needs to change.
 */
class AwsSnsSmsProvider implements SmsProviderInterface
{
    public function send(string $mobile, string $message): bool
    {
        if (! class_exists(\Aws\Sns\SnsClient::class)) {
            Log::warning('AWS SNS SMS provider is active but the aws/aws-sdk-php package is not installed. Run: composer require aws/aws-sdk-php');

            return false;
        }

        $config = config('sms.providers.sns');

        $client = new \Aws\Sns\SnsClient([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        $result = $client->publish([
            'Message' => $message,
            'PhoneNumber' => $mobile,
        ]);

        return ! empty($result->get('MessageId'));
    }
}
