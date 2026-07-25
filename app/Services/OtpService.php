<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Models\MobileVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Owns the full OTP lifecycle — generate, send, verify, expire — for both
 * registration (Marketing User / Dealer / Farmer creation) and login. This
 * is the ONLY place that reads/writes mobile_verifications, so every rule
 * (6-digit numeric, 5-minute expiry, 5 verify attempts, 3 resends, latest-
 * OTP-only, hashed at rest) is enforced in exactly one place.
 *
 * "Verified" is proven server-side via a session flag set on successful
 * verify() — never by trusting a client-submitted field — and must be
 * consumed (see consume()) by the caller once it's actually used to create
 * a record, so one verification can't be replayed to create several.
 */
class OtpService
{
    public function __construct(
        private readonly SmsProviderInterface $sms,
    ) {}

    /**
     * Generate, persist (hashed) and send a new OTP. Invalidates any
     * still-pending OTP for the same mobile+purpose first ("Old OTP
     * becomes invalid after resend" / "Latest OTP only").
     */
    public function send(string $mobile, string $purpose, ?int $userId, Request $request): array
    {
        $mobile = $this->normalize($mobile);

        $throttleKey = "otp-send:{$purpose}:{$mobile}";

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return ['ok' => false, 'message' => "Please wait {$seconds} seconds before requesting another OTP."];
        }

        $previous = $this->latestPending($mobile, $purpose);

        if ($previous && ! $previous->isExpired() && $previous->resend_count >= MobileVerification::MAX_RESENDS) {
            return ['ok' => false, 'message' => 'Maximum OTP resend attempts reached. Please try again later.'];
        }

        RateLimiter::hit($throttleKey, 20);

        if ($previous) {
            $previous->update(['status' => 'expired']);
        }

        $otp = (string) random_int(100000, 999999);

        $record = MobileVerification::create([
            'user_id' => $userId,
            'mobile' => $mobile,
            'otp' => Hash::make($otp),
            'purpose' => $purpose,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(MobileVerification::EXPIRES_IN_MINUTES),
            'attempts' => 0,
            'resend_count' => $previous ? $previous->resend_count + 1 : 0,
            'ip_address' => $request->ip(),
            'device' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $sent = $this->sms->send($mobile, "Your Arcadia Biotech ERP OTP is {$otp}. Valid for ".MobileVerification::EXPIRES_IN_MINUTES.' minutes. Do not share this with anyone.');

        Log::info('[OTP Sent]', ['mobile' => $mobile, 'purpose' => $purpose, 'record_id' => $record->id, 'sent' => $sent]);

        $isDevLogDriver = app()->environment('local') && config('sms.default') === 'log';

        return [
            'ok' => true,
            'message' => 'OTP sent to '.$this->mask($mobile).'.',
            'expires_in' => MobileVerification::EXPIRES_IN_MINUTES * 60,
            'resend_count' => $record->resend_count,
            'max_resends' => MobileVerification::MAX_RESENDS,
            // Only ever surfaced when SMS_PROVIDER=log AND running locally —
            // lets the whole OTP flow be tested end-to-end with no real SMS
            // account configured. Never populated against a real provider.
            'dev_otp' => $isDevLogDriver ? $otp : null,
        ];
    }

    public function verify(string $mobile, string $purpose, string $otp): array
    {
        $mobile = $this->normalize($mobile);

        $record = $this->latestPending($mobile, $purpose);

        if (! $record) {
            Log::warning('[OTP Failed] no pending OTP', ['mobile' => $mobile, 'purpose' => $purpose]);

            return ['ok' => false, 'message' => 'No pending OTP for this number. Please request a new OTP.'];
        }

        if ($record->isExpired()) {
            $record->update(['status' => 'expired']);
            Log::warning('[OTP Failed] expired', ['mobile' => $mobile, 'purpose' => $purpose, 'record_id' => $record->id]);

            return ['ok' => false, 'message' => 'OTP has expired. Please request a new one.'];
        }

        if ($record->attempts >= MobileVerification::MAX_ATTEMPTS) {
            $record->update(['status' => 'failed']);
            Log::warning('[OTP Failed] max attempts', ['mobile' => $mobile, 'purpose' => $purpose, 'record_id' => $record->id]);

            return ['ok' => false, 'message' => 'Maximum verification attempts exceeded. Please request a new OTP.'];
        }

        $record->increment('attempts');

        if (! Hash::check($otp, $record->otp)) {
            $remaining = MobileVerification::MAX_ATTEMPTS - $record->attempts;
            Log::warning('[OTP Failed] mismatch', ['mobile' => $mobile, 'purpose' => $purpose, 'record_id' => $record->id, 'remaining' => $remaining]);

            return ['ok' => false, 'message' => "Incorrect OTP. {$remaining} attempt(s) remaining."];
        }

        $record->update(['status' => 'verified', 'verified_at' => now()]);

        session(["otp_verified.{$purpose}.{$mobile}" => now()->timestamp]);

        Log::info('[OTP Verified]', ['mobile' => $mobile, 'purpose' => $purpose, 'record_id' => $record->id]);

        return ['ok' => true, 'message' => 'Mobile number verified successfully.'];
    }

    /**
     * Server-side proof that this browser session actually completed OTP
     * verification for this mobile+purpose recently. This — never a
     * client-submitted field — is what registration controllers check
     * before persisting a Marketing User / Dealer / Farmer.
     */
    public function isVerified(string $mobile, string $purpose, int $withinMinutes = 30): bool
    {
        $mobile = $this->normalize($mobile);
        $timestamp = session("otp_verified.{$purpose}.{$mobile}");

        if (! $timestamp || now()->subMinutes($withinMinutes)->timestamp > $timestamp) {
            return false;
        }

        return MobileVerification::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->where('status', 'verified')
            ->where('verified_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }

    /**
     * Consume the verification proof so it can't be replayed to create a
     * second record without verifying again. Call this once, right after
     * the gated record is actually saved.
     */
    public function consume(string $mobile, string $purpose): void
    {
        session()->forget('otp_verified.'.$purpose.'.'.$this->normalize($mobile));
    }

    private function latestPending(string $mobile, string $purpose): ?MobileVerification
    {
        return MobileVerification::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function normalize(string $mobile): string
    {
        return preg_replace('/\D/', '', $mobile) ?? $mobile;
    }

    private function mask(string $mobile): string
    {
        return str_repeat('*', max(strlen($mobile) - 4, 0)).substr($mobile, -4);
    }
}
