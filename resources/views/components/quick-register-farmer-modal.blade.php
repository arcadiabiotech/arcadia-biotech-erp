@props(['states', 'districts', 'talukas', 'villages', 'name' => 'register-farmer'])

{{--
    Reusable "register new farmer" quick-add modal, used from both the
    dispatch plan form and the booking form so a new farmer can be
    registered inline without navigating away from an in-progress form.

    Contract expected from the including page's outer x-data:
    - quickAddDealerId (string getter/property): dealer_id to attribute the new farmer to.
    - quickAddDealerName (string getter): dealer name shown in the modal header.
    - farmerRegistered(farmer): called with the created farmer on success.
--}}
<x-modal :name="$name" maxWidth="lg">
    {{-- x-if fully remounts this block each time the modal opens, which
         resets both the local quick-add fields and the nested
         otp-mobile-gate's OTP state — simpler than hand-rolling a
         reset() for a component we don't own the internals of. --}}
    <template x-if="show">
        <div
            x-data="{
                farmerName: '',
                stateId: '',
                districtId: '',
                talukaId: '',
                villageId: '',
                submitting: false,
                errors: {},
                districts: {{ Js::from($districts->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name, 'state_id' => (string) $d->state_id])) }},
                talukas: {{ Js::from($talukas->map(fn ($t) => ['id' => (string) $t->id, 'name' => $t->name, 'district_id' => (string) $t->district_id])) }},
                villages: {{ Js::from($villages->map(fn ($v) => ['id' => (string) $v->id, 'name' => $v->name, 'taluka_id' => (string) $v->taluka_id])) }},
                get visibleDistricts() { return this.districts.filter(d => d.state_id === this.stateId) },
                get visibleTalukas() { return this.talukas.filter(t => t.district_id === this.districtId) },
                get visibleVillages() { return this.villages.filter(v => v.taluka_id === this.talukaId) },
                async submit($el) {
                    this.errors = {};
                    const form = $el.closest('form');
                    if (! window.arcadiaCheckOtpVerified(form, 'mobile_verified_flag')) return;

                    this.submitting = true;
                    try {
                        const res = await fetch('{{ route('farmers.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                farmer_name: this.farmerName,
                                dealer_id: quickAddDealerId,
                                mobile: form.querySelector('[name=mobile]').value,
                                state_id: this.stateId,
                                district_id: this.districtId,
                                taluka_id: this.talukaId,
                                village_id: this.villageId,
                                status: 1,
                            }),
                        });
                        const data = await res.json();
                        this.submitting = false;
                        if (res.ok && data.ok) {
                            farmerRegistered(data.farmer);
                            this.$dispatch('close');
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (e) {
                        this.submitting = false;
                        this.errors = { farmer_name: ['Something went wrong. Please try again.'] };
                    }
                },
            }"
            class="p-6"
        >
            <h2 class="text-lg font-bold text-slate-900">Register new farmer</h2>
            <p class="mt-1 text-sm text-slate-500">For dealer: <span class="font-semibold text-slate-700" x-text="quickAddDealerName"></span></p>

            <form class="mt-4 space-y-4" @submit.prevent="submit($el)">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Farmer name</label>
                    <input name="farmer_name" x-model="farmerName" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <template x-if="errors.farmer_name"><p class="mt-1 text-sm text-rose-600" x-text="errors.farmer_name[0]"></p></template>
                </div>

                <div>
                    <x-otp-mobile-gate context="farmer" name="mobile" />
                    <template x-if="errors.mobile"><p class="mt-1 text-sm text-rose-600" x-text="errors.mobile[0]"></p></template>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">State</label>
                        <select x-model="stateId" @change="districtId = ''; talukaId = ''; villageId = ''" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select state —</option>
                            @foreach($states as $state)<option value="{{ $state->id }}">{{ $state->name }}</option>@endforeach
                        </select>
                        <template x-if="errors.state_id"><p class="mt-1 text-sm text-rose-600" x-text="errors.state_id[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">District</label>
                        <select x-model="districtId" @change="talukaId = ''; villageId = ''" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select district —</option>
                            <template x-for="district in visibleDistricts" :key="district.id"><option :value="district.id" x-text="district.name"></option></template>
                        </select>
                        <template x-if="errors.district_id"><p class="mt-1 text-sm text-rose-600" x-text="errors.district_id[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Taluka</label>
                        <select x-model="talukaId" @change="villageId = ''" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select taluka —</option>
                            <template x-for="taluka in visibleTalukas" :key="taluka.id"><option :value="taluka.id" x-text="taluka.name"></option></template>
                        </select>
                        <template x-if="errors.taluka_id"><p class="mt-1 text-sm text-rose-600" x-text="errors.taluka_id[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Village</label>
                        <select x-model="villageId" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select village —</option>
                            <template x-for="village in visibleVillages" :key="village.id"><option :value="village.id" x-text="village.name"></option></template>
                        </select>
                        <template x-if="errors.village_id"><p class="mt-1 text-sm text-rose-600" x-text="errors.village_id[0]"></p></template>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="$dispatch('close')" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button :disabled="submitting" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50" x-text="submitting ? 'Registering…' : 'Register farmer'"></button>
                </div>
            </form>
        </div>
    </template>
</x-modal>
