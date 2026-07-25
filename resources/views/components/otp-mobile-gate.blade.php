@props(['context', 'name' => 'mobile', 'value' => null, 'label' => 'Mobile'])

@php
    $initialMobile = old($name, $value);
@endphp

<div
    x-data="{
        context: '{{ $context }}',
        mobile: '{{ $initialMobile }}',
        stage: 'idle',
        verified: false,
        otp: '',
        sending: false,
        verifying: false,
        message: '',
        error: '',
        devOtp: null,
        seconds: 0,
        timer: null,
        sendUrl: '{{ route('otp.send') }}',
        verifyUrl: '{{ route('otp.verify') }}',
        csrf: document.querySelector('meta[name=csrf-token]').content,
        onMobileInput() { this.verified = false; this.stage = 'idle'; this.message = ''; this.error = ''; },
        startTimer(s) {
            this.seconds = s;
            clearInterval(this.timer);
            this.timer = setInterval(() => { this.seconds > 0 ? this.seconds-- : clearInterval(this.timer); }, 1000);
        },
        async sendOtp() {
            this.error = ''; this.message = '';
            if (! /^\d{10}$/.test(this.mobile)) { this.error = 'Enter a valid 10-digit mobile number.'; return; }
            this.sending = true;
            try {
                const res = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ mobile: this.mobile, context: this.context }),
                });
                const data = await res.json();
                this.sending = false;
                if (data.ok) {
                    this.stage = 'sent';
                    this.message = data.message;
                    this.devOtp = data.dev_otp ?? null;
                    this.startTimer(30);
                } else {
                    this.error = data.message;
                }
            } catch (e) {
                this.sending = false;
                this.error = 'Could not send OTP. Please try again.';
            }
        },
        async verifyOtp() {
            this.error = '';
            if (! /^\d{6}$/.test(this.otp)) { this.error = 'Enter the 6-digit OTP.'; return; }
            this.verifying = true;
            try {
                const res = await fetch(this.verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ mobile: this.mobile, otp: this.otp }),
                });
                const data = await res.json();
                this.verifying = false;
                if (data.ok) {
                    this.stage = 'verified';
                    this.verified = true;
                    this.message = data.message;
                } else {
                    this.error = data.message;
                }
            } catch (e) {
                this.verifying = false;
                this.error = 'Could not verify OTP. Please try again.';
            }
        },
    }"
>
    <label class="text-sm font-semibold text-slate-700">{{ $label }}</label>
    <div class="mt-2 flex flex-wrap gap-2">
        <input
            type="text" name="{{ $name }}" x-model="mobile" @input="onMobileInput()"
            maxlength="10" inputmode="numeric" required :readonly="stage === 'verified'"
            class="block min-w-0 flex-1 rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 read-only:bg-slate-50 read-only:text-slate-500">
        <button
            type="button" @click="sendOtp()" :disabled="sending || stage === 'verified' || seconds > 0"
            class="shrink-0 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            x-text="stage === 'verified' ? 'Verified ✓' : (sending ? 'Sending…' : (seconds > 0 ? `Resend in ${seconds}s` : (stage === 'sent' ? 'Resend OTP' : 'Send OTP')))">
        </button>
    </div>
    @error($name)<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror

    <div x-show="stage === 'sent'" x-cloak class="mt-3 flex flex-wrap items-center gap-2">
        <input type="text" inputmode="numeric" maxlength="6" x-model="otp" placeholder="6-digit OTP"
            class="block w-40 rounded-xl border-slate-300 text-center tracking-[0.3em] shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <button type="button" @click="verifyOtp()" :disabled="verifying"
            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            x-text="verifying ? 'Verifying…' : 'Verify OTP'"></button>
    </div>

    <p x-show="message" x-cloak x-text="message" class="mt-2 text-sm font-medium text-emerald-600"></p>
    <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm font-medium text-rose-600"></p>
    <p x-show="devOtp" x-cloak class="mt-2 text-xs text-amber-700">DEV MODE (SMS_PROVIDER=log) — OTP: <strong x-text="devOtp"></strong></p>

    <input type="hidden" id="{{ $name }}_verified_flag" :value="verified ? '1' : '0'">
</div>
