@props(['varieties', 'varietyStocks' => [], 'name' => 'create-booking'])

{{--
    "Create Booking" quick-add modal for the Dispatch Plan form — a Dispatch
    Plan is always built from real Bookings now (never a bare Farmer), so
    this replaces the old "register new farmer" shortcut at the row level:
    if the dealer has no bookings to pick from yet, this creates one inline
    (optionally registering its farmer inline too, via the existing
    quick-register-farmer-modal) without ever leaving the plan form.

    Contract expected from the including page's outer x-data:
    - quickAddDealerId / quickAddDealerName: dealer_id/name this booking is for.
    - farmers (array): every preloaded farmer, each {id, name, dealer_id} —
      filtered here to the current dealer.
    - newBooking (object): {farmerId, variety, plantQty, plantRate,
      bookingDate, remarks, submitting, errors} — reset by the page each
      time this modal is opened.
    - bookingCreated(booking): called with the created booking on success.
--}}
<x-modal :name="$name" maxWidth="lg">
    <template x-if="show">
        <div class="p-6">
            <h2 class="text-lg font-bold text-slate-900">Create booking</h2>
            <p class="mt-1 text-sm text-slate-500">For dealer: <span class="font-semibold text-slate-700" x-text="quickAddDealerName"></span></p>

            <form class="mt-4 space-y-4" @submit.prevent="submitNewBooking()">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Farmer</label>
                    <div class="mt-2 flex items-center gap-3">
                        <select x-model="newBooking.farmerId" required class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select farmer —</option>
                            <template x-for="farmer in farmers.filter(f => f.dealer_id === quickAddDealerId)" :key="farmer.id">
                                <option :value="farmer.id" x-text="farmer.name" :selected="farmer.id === newBooking.farmerId"></option>
                            </template>
                        </select>
                        <button type="button" @click="$dispatch('open-modal', 'register-farmer')" class="shrink-0 whitespace-nowrap text-xs font-semibold text-emerald-600 hover:text-emerald-800">+ Register new</button>
                    </div>
                    <template x-if="errors.farmer_id"><p class="mt-1 text-sm text-rose-600" x-text="errors.farmer_id[0]"></p></template>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Variety</label>
                        <select x-model="newBooking.variety" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Select variety —</option>
                            @foreach($varieties as $variety)
                                @php($available = $varietyStocks[$variety] ?? 0)
                                <option value="{{ $variety }}" @disabled($available <= 0)>{{ $variety }} — {{ $available > 0 ? number_format($available).' available' : 'Not available' }}</option>
                            @endforeach
                        </select>
                        <template x-if="errors.variety"><p class="mt-1 text-sm text-rose-600" x-text="errors.variety[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Booking date</label>
                        <input type="date" x-model="newBooking.bookingDate" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <template x-if="errors.booking_date"><p class="mt-1 text-sm text-rose-600" x-text="errors.booking_date[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Booking quantity</label>
                        <input type="number" min="1" x-model="newBooking.plantQty" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <template x-if="errors.plant_qty"><p class="mt-1 text-sm text-rose-600" x-text="errors.plant_qty[0]"></p></template>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Rate per plant</label>
                        <input type="number" min="0" step="0.01" x-model="newBooking.plantRate" required class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <template x-if="errors.plant_rate"><p class="mt-1 text-sm text-rose-600" x-text="errors.plant_rate[0]"></p></template>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea x-model="newBooking.remarks" rows="2" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    <template x-if="errors.remarks"><p class="mt-1 text-sm text-rose-600" x-text="errors.remarks[0]"></p></template>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="$dispatch('close')" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button :disabled="newBooking.submitting" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50" x-text="newBooking.submitting ? 'Saving…' : 'Save booking'"></button>
                </div>
            </form>
        </div>
    </template>
</x-modal>
