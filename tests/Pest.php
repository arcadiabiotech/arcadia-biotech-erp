<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fakes a completed OTP verification for the given mobile+purpose, the same
 * way OtpService::verify() would leave things: a session proof flag plus a
 * "verified" MobileVerification row — so tests can exercise OTP-gated
 * registration flows (Dealer/Farmer/Marketing user creation) without going
 * through the actual send/verify HTTP round trip.
 */
function verifyMobileOtp(string $mobile, string $purpose = 'registration'): void
{
    $mobile = preg_replace('/\D/', '', $mobile);

    session(["otp_verified.{$purpose}.{$mobile}" => now()->timestamp]);

    \App\Models\MobileVerification::create([
        'mobile' => $mobile,
        'otp' => bcrypt('123456'),
        'purpose' => $purpose,
        'status' => 'verified',
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);
}
