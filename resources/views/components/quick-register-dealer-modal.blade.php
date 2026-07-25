@props(['name' => 'register-dealer'])

{{--
    Reusable "register new dealer" quick-add modal, used from both the
    dispatch plan form and the booking form so a new dealer can be
    registered inline without navigating away from an in-progress form.
    Collects the fields DealerStoreRequest requires (firm_name, dealer_name,
    mobile+OTP) plus address — the rest (location cascade, GST/PAN, credit
    limit, etc.) stays nullable, so full editing stays available later on
    dealers.edit.

    Contract expected from the including page's outer x-data:
    - dealerRegistered(dealer): called with the created dealer on success.
--}}
<x-modal :name="$name" maxWidth="lg">
    <template x-if="show">
        <div
            x-data="{
                firmName: '',
                dealerName: '',
                address: '',
                submitting: false,
                errors: {},
                async submit($el) {
                    this.errors = {};
                    const form = $el.closest('form');
                    if (! window.arcadiaCheckOtpVerified(form, 'mobile_verified_flag')) return;

                    this.submitting = true;
                    try {
                        const res = await fetch('{{ route('dealers.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                firm_name: this.firmName,
                                dealer_name: this.dealerName,
                                mobile: form.querySelector('[name=mobile]').value,
                                address: this.address,
                                status: 1,
                            }),
                        });
                        const data = await res.json();
                        this.submitting = false;
                        if (res.ok && data.ok) {
                            dealerRegistered(data.dealer);
                            this.$dispatch('close');
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (e) {
                        this.submitting = false;
                        this.errors = { firm_name: ['Something went wrong. Please try again.'] };
                    }
                },
            }"
            class="p-6"
        >
            <h2 class="text-lg font-bold text-slate-900">Register new dealer</h2>

            <form class="mt-4 space-y-4" @submit.prevent="submit($el)">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Firm name</label>
                    <input name="firm_name" x-model="firmName" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <template x-if="errors.firm_name"><p class="mt-1 text-sm text-rose-600" x-text="errors.firm_name[0]"></p></template>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Dealer name</label>
                    <input name="dealer_name" x-model="dealerName" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <template x-if="errors.dealer_name"><p class="mt-1 text-sm text-rose-600" x-text="errors.dealer_name[0]"></p></template>
                </div>

                <div>
                    <x-otp-mobile-gate context="dealer" name="mobile" />
                    <template x-if="errors.mobile"><p class="mt-1 text-sm text-rose-600" x-text="errors.mobile[0]"></p></template>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Address <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea x-model="address" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    <template x-if="errors.address"><p class="mt-1 text-sm text-rose-600" x-text="errors.address[0]"></p></template>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="$dispatch('close')" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button :disabled="submitting" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50" x-text="submitting ? 'Registering…' : 'Register dealer'"></button>
                </div>
            </form>
        </div>
    </template>
</x-modal>
