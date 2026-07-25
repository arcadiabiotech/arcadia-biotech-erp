<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared "enter mobile → send OTP → verify OTP" endpoints reused by every
 * in-app registration flow (Admin creating a Marketing User, Marketing
 * creating a Dealer, Dealer creating a Farmer). All three forms call the
 * same two endpoints; only the `context` differs, which is used purely to
 * pre-check "this mobile is already taken" against the right table before
 * wasting an OTP send — the real, authoritative uniqueness check still
 * happens in each StoreRequest at submit time.
 *
 * Login OTP is a separate, unauthenticated flow — see
 * Auth\AuthenticatedSessionController — since there is no logged-in user
 * yet at that point.
 */
class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

    public function send(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'context' => ['required', Rule::in(['user', 'dealer', 'farmer'])],
        ]);

        if ($this->mobileTaken($data['context'], $data['mobile'])) {
            return response()->json([
                'ok' => false,
                'message' => 'This mobile number is already registered.',
            ], 422);
        }

        $result = $this->otp->send($data['mobile'], 'registration', $request->user()->id, $request);

        return response()->json($result, $result['ok'] ? 200 : 429);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'otp' => ['required', 'digits:6'],
        ]);

        $result = $this->otp->verify($data['mobile'], 'registration', $data['otp']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    private function mobileTaken(string $context, string $mobile): bool
    {
        return match ($context) {
            'user' => User::where('mobile', $mobile)->exists(),
            'dealer' => Dealer::where('mobile', $mobile)->exists(),
            'farmer' => Farmer::where('mobile', $mobile)->exists(),
        };
    }
}
