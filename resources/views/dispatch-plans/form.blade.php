<x-app-layout>
    <div
        class="mx-auto max-w-4xl"
        x-data="{
            bookings: {{ Js::from($bookings->map(fn ($b) => [
                'id' => (string) $b->id,
                'booking_no' => $b->booking_no,
                'farmer_id' => (string) $b->farmer_id,
                'farmer_name' => $b->farmer?->farmer_name,
                'variety' => $b->variety,
                'dealer_id' => (string) $b->dealer_id,
                'sale_type' => $b->sale_type,
                'booked_qty' => $b->plant_qty,
                'dispatched_qty' => $b->dispatched_qty,
                'balance_qty' => $b->balance_qty,
            ])) }},
            farmers: {{ Js::from($farmers->map(fn ($f) => ['id' => (string) $f->id, 'name' => $f->farmer_name, 'dealer_id' => (string) $f->dealer_id])) }},
            dealers: {{ Js::from($dealers->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->dealer_name])) }},
            groups: {{ Js::from($initialDealerGroups) }},
            quickAddGroupIndex: null,
            newBooking: { farmerId: '', variety: '', plantQty: '', plantRate: '', bookingDate: '{{ now()->toDateString() }}', remarks: '', submitting: false, errors: {} },

            addGroup() { this.groups.push({ dealerId: '', rows: [] }) },
            removeGroup(gi) { this.groups.splice(gi, 1) },
            addRow(gi) { this.groups[gi].rows.push({ bookingId: '', dispatchQty: '' }) },
            removeRow(gi, ri) { this.groups[gi].rows.splice(ri, 1) },

            bookingById(id) { return this.bookings.find(b => b.id === id) ?? null },
            usedBookingIds(excludeGi, excludeRi) {
                const used = [];
                this.groups.forEach((g, gi) => g.rows.forEach((r, ri) => {
                    if (r.bookingId && ! (gi === excludeGi && ri === excludeRi)) used.push(r.bookingId);
                }));
                return used;
            },
            availableBookingsFor(gi, ri) {
                const dealerId = this.groups[gi].dealerId;
                const used = this.usedBookingIds(gi, ri);
                return this.bookings.filter(b => b.dealer_id === dealerId && ! used.includes(b.id));
            },
            onBookingSelected(gi, ri) {
                const row = this.groups[gi].rows[ri];
                const booking = this.bookingById(row.bookingId);
                row.dispatchQty = booking ? String(booking.balance_qty) : '';
            },
            get totalPlants() { return this.groups.reduce((sum, g) => sum + g.rows.reduce((s, r) => s + (parseInt(r.dispatchQty) || 0), 0), 0) },

            openCreateBooking(gi) {
                this.quickAddGroupIndex = gi;
                this.newBooking = { farmerId: '', variety: '', plantQty: '', plantRate: '', bookingDate: new Date().toISOString().slice(0, 10), remarks: '', submitting: false, errors: {} };
                this.$dispatch('open-modal', 'create-booking');
            },
            get quickAddDealerId() { return this.quickAddGroupIndex !== null ? this.groups[this.quickAddGroupIndex].dealerId : '' },
            get quickAddDealerName() {
                const dealer = this.dealers.find(d => d.id === this.quickAddDealerId);
                return dealer ? dealer.name : '';
            },

            async submitNewBooking() {
                this.newBooking.errors = {};
                this.newBooking.submitting = true;
                try {
                    const res = await fetch('{{ route('bookings.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({
                            dealer_id: this.quickAddDealerId,
                            farmer_id: this.newBooking.farmerId,
                            variety: this.newBooking.variety,
                            booking_date: this.newBooking.bookingDate,
                            plant_qty: this.newBooking.plantQty,
                            plant_rate: this.newBooking.plantRate,
                            remarks: this.newBooking.remarks,
                        }),
                    });
                    const data = await res.json();
                    this.newBooking.submitting = false;
                    if (res.ok && data.ok) {
                        this.bookingCreated(data.booking);
                        this.$dispatch('close');
                    } else {
                        this.newBooking.errors = data.errors || {};
                    }
                } catch (e) {
                    this.newBooking.submitting = false;
                    this.newBooking.errors = { plant_qty: ['Something went wrong. Please try again.'] };
                }
            },

            bookingCreated(booking) {
                const normalized = {
                    id: String(booking.id),
                    booking_no: booking.booking_no,
                    farmer_id: String(booking.farmer_id),
                    farmer_name: booking.farmer_name,
                    variety: booking.variety,
                    dealer_id: String(booking.dealer_id),
                    sale_type: booking.sale_type,
                    booked_qty: booking.booked_qty,
                    dispatched_qty: booking.dispatched_qty,
                    balance_qty: booking.balance_qty,
                };
                this.bookings.push(normalized);
                if (this.quickAddGroupIndex !== null) {
                    this.groups[this.quickAddGroupIndex].rows.push({ bookingId: normalized.id, dispatchQty: String(normalized.balance_qty) });
                }
            },

            farmerRegistered(farmer) {
                this.farmers.push({ id: String(farmer.id), name: farmer.farmer_name, dealer_id: String(farmer.dealer_id) });
                this.newBooking.farmerId = String(farmer.id);
            },
            dealerRegistered(dealer) {
                this.dealers.push({ id: String(dealer.id), name: dealer.dealer_name });
                this.addGroup();
                const gi = this.groups.length - 1;
                // The new group's <select> (and its dealer <option>s) are
                // mounted by this same addGroup() call — assigning dealerId
                // in the same tick can race ahead of that mount, so the
                // browser <select> silently ignores it (no matching <option>
                // exists yet). $nextTick waits for the DOM to catch up first.
                this.$nextTick(() => { this.groups[gi].dealerId = String(dealer.id); });
            },
        }"
    >
        <div class="mb-8">
            <a href="{{ route('dispatch-plans.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← Back to dispatch plans</a>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $plan->exists ? 'Edit dispatch plan' : 'New dispatch plan' }}</h1>
            <p class="mt-2 text-sm text-slate-500">Set the date and route, then pick each dealer's active bookings and how much of each to dispatch — you'll add vehicles on the next screen.</p>
        </div>

        <form
            method="POST" action="{{ $plan->exists ? route('dispatch-plans.update', $plan) : route('dispatch-plans.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @if($plan->exists) @method('PUT') @endif

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Planning date</label>
                    <input type="date" name="plan_date" required value="{{ old('plan_date', $plan->plan_date?->toDateString()) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('plan_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Route <span class="font-normal text-slate-400">(optional)</span></label>
                    <input name="route" value="{{ old('route', $plan->route) }}" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('route')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-6">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Dealers &amp; bookings</h2>
                    <p class="mt-1 text-xs text-slate-500">Total dispatch quantity below is calculated automatically from these rows.</p>
                </div>
                <p class="text-lg font-bold text-slate-900">Total: <span x-text="totalPlants"></span> plants</p>
            </div>

            @php($estimateErrors = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'farmer_estimates')))
            @if($estimateErrors->isNotEmpty())
                <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                    <ul class="list-disc pl-4">
                        @foreach($estimateErrors as $key)<li>{{ $errors->first($key) }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 space-y-4">
                <template x-for="(group, gi) in groups" :key="gi">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <label class="text-xs font-semibold text-slate-600">Dealer</label>
                                <select x-model="group.dealerId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">— Select dealer —</option>
                                    <template x-for="dealer in dealers" :key="dealer.id"><option :value="dealer.id" x-text="dealer.name" :selected="dealer.id === group.dealerId"></option></template>
                                </select>
                            </div>
                            <button type="button" @click="removeGroup(gi)" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Remove dealer</button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <template x-for="(row, ri) in group.rows" :key="ri">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <label class="text-xs font-semibold text-slate-600">Booking</label>
                                            <select
                                                :name="'farmer_estimates['+gi+'_'+ri+'][booking_id]'"
                                                x-model="row.bookingId"
                                                @change="onBookingSelected(gi, ri)"
                                                required
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">— Select booking —</option>
                                                <template x-for="booking in availableBookingsFor(gi, ri)" :key="booking.id">
                                                    <option :value="booking.id" x-text="booking.booking_no + (booking.sale_type === 'spot' ? ' [Spot]' : '') + ' — ' + booking.farmer_name + ' — ' + booking.variety + ' — Balance: ' + booking.balance_qty" :selected="booking.id === row.bookingId"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <button type="button" @click="removeRow(gi, ri)" class="rounded-lg px-2 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">✕</button>
                                    </div>

                                    <template x-if="row.bookingId && bookingById(row.bookingId)">
                                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-xs sm:grid-cols-4" x-data="{ get b() { return bookingById(row.bookingId) } }">
                                            <div><p class="text-slate-500">Farmer</p><p class="font-semibold text-slate-800" x-text="b.farmer_name"></p></div>
                                            <div><p class="text-slate-500">Variety</p><p class="font-semibold text-slate-800" x-text="b.variety"></p></div>
                                            <div><p class="text-slate-500">Booked qty</p><p class="font-semibold text-slate-800" x-text="b.booked_qty"></p></div>
                                            <div><p class="text-slate-500">Already dispatched</p><p class="font-semibold text-slate-800" x-text="b.dispatched_qty"></p></div>
                                            <div class="col-span-2 sm:col-span-1"><p class="text-slate-500">Balance</p><p class="font-semibold text-emerald-700" x-text="b.balance_qty"></p></div>
                                            <div class="col-span-2">
                                                <label class="text-slate-500">Dispatch quantity</label>
                                                <input
                                                    type="number" min="1" :max="b.balance_qty"
                                                    :name="'farmer_estimates['+gi+'_'+ri+'][dispatch_qty]'"
                                                    x-model="row.dispatchQty"
                                                    required
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-semibold shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <button type="button" @click="addRow(gi)" :disabled="! group.dealerId" class="text-xs font-semibold text-blue-600 hover:text-blue-800 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:text-slate-300">+ Add booking</button>
                            <button
                                type="button" @click="openCreateBooking(gi)" :disabled="! group.dealerId"
                                :title="! group.dealerId ? 'Select a dealer first' : ''"
                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-800 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:text-slate-300">
                                + Create booking
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                <button type="button" @click="addGroup()" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-100">+ Add dealer</button>
                <button type="button" @click="$dispatch('open-modal', 'register-dealer')" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">+ Register new dealer</button>
            </div>

            <div class="mt-8">
                <label class="text-sm font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                <textarea name="remarks" rows="3" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('remarks', $plan->remarks) }}</textarea>
                @error('remarks')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6">
                <a href="{{ route('dispatch-plans.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $plan->exists ? 'Save changes' : 'Create plan' }}</button>
            </div>
        </form>

        <x-quick-create-booking-modal :varieties="\App\Models\Booking::VARIETIES" :varietyStocks="$varietyStocks" />
        <x-quick-register-farmer-modal :states="$states" :districts="$districts" :talukas="$talukas" :villages="$villages" />
        <x-quick-register-dealer-modal />
    </div>
</x-app-layout>
