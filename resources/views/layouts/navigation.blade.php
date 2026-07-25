@php
    /**
     * Role-based navigation refactor — the sidebar is now a strict per-role
     * whitelist (built from a single match() below) rather than an
     * accumulation of independently-gated items. That accumulation pattern
     * is what let items like Dealers/Farmers/States/Districts/Talukas/
     * Villages render completely unconditionally before this refactor —
     * every item below is explicitly assigned to a role, nothing is shown
     * by default.
     *
     * IMPORTANT: this only controls what's *shown*. The actual access
     * control lives in route middleware and Policies (DealerPolicy,
     * FarmerPolicy, BookingPolicy, DispatchPolicy, InvoicePolicy,
     * PaymentPolicy, UserPolicy, VehiclePolicy, and role: middleware on
     * routes/web.php) — hiding a link here never substitutes for that.
     */
    $navUser = auth()->user();
    $roleName = $navUser?->role?->name;

    $dashboard = ['Dashboard', route('dashboard'), 'dashboard', 'M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z M9 22v-6h6v6'];
    $dealers = ['Dealers', route('dealers.index'), 'dealers.*', 'M4 7h16v13H4z M8 7V4h8v3 M4 12h16'];
    $myDealers = ['My Dealers', route('dealers.index'), 'dealers.*', 'M4 7h16v13H4z M8 7V4h8v3 M4 12h16'];
    $dealerAssignments = ['Dealer Assignments', route('dealer-assignments.index'), 'dealer-assignments.*', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z'];
    $farmers = ['Farmers', route('farmers.index'), 'farmers.*', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z'];
    $myFarmers = ['My Farmers', route('farmers.index'), 'farmers.*', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z'];
    $states = ['States', route('states.index'), 'states.*', 'M12 22a10 10 0 100-20 10 10 0 000 20z M2 12h20'];
    $districts = ['Districts', route('districts.index'), 'districts.*', 'M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3V6z M9 3v15 M15 6v15'];
    $talukas = ['Talukas', route('talukas.index'), 'talukas.*', 'M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1118 0z'];
    $villages = ['Villages', route('villages.index'), 'villages.*', 'M3 21h18 M5 21V7l7-4 7 4v14 M9 21v-5h6v5'];
    $bookings = ['Bookings', route('bookings.index'), 'bookings.*', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'];
    $approvedBookings = ['Approved Bookings', route('bookings.index'), 'bookings.*', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2 M9 5a3 3 0 006 0'];
    $dispatch = ['Dispatch', route('dispatches.index'), 'dispatches.*', 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'];
    $dispatchView = ['Dispatch View', route('dispatches.index'), 'dispatches.*', 'M3 7h11v10H3z M14 10h4l3 3v4h-7v-7z'];
    $dispatchPlanning = ['Dispatch Planning', route('dispatch-plans.index'), 'dispatch-plans.*', 'M9 20l-5.5 2 1-6L2 12l6-1 4-6 4 6 6 1-4.5 4.5 1 6z'];
    $vehicles = ['Vehicles', route('vehicles.index'), 'vehicles.*', 'M3 13l2-5a2 2 0 012-1h6a2 2 0 012 1l2 5 M3 13h16v4H3z M6 17a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z M18 17a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z'];
    $challans = ['Challans', route('challans.index'), 'challans.*', 'M6 2h9l4 4v16H6z M14 2v5h5 M9 13h6 M9 17h6'];
    $invoices = ['Invoice', route('invoices.index'), 'invoices.*', 'M6 2h12v20l-3-2-3 2-3 2-3-2-3 2V2z M9 8h6 M9 12h6'];
    $payments = ['Payments', route('payments.index'), 'payments.*', 'M3 5h18a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2z M1 10h22'];
    $ledger = ['Ledger', route('ledger.outstanding'), 'ledger.outstanding', 'M9 17v-6h6v6 M4 21h16 M6 21V9l6-5 6 5v12'];
    $stock = ['Stock', route('variety-stocks.index'), 'variety-stocks.*', 'M21 16V8l-9-5-9 5v8l9 5 9-5z'];
    $reports = ['Reports', route('reports.index'), 'reports.*', 'M4 20V10 M10 20V4 M16 20v-7 M22 20H2'];
    // Users and Marketing both route to users.index (Marketing is a filtered
    // lens via ?role=marketing), so routeIs('users.*') alone can't tell them
    // apart — add an explicit active-match on the role query param so
    // selecting one doesn't also highlight the other.
    $users = ['Users', route('users.index'), 'users.*', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z', fn () => request()->routeIs('users.*') && request('role') !== 'marketing'];
    $marketingUsers = ['Marketing', route('users.index', ['role' => 'marketing']), 'users.*', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z', fn () => request()->routeIs('users.*') && request('role') === 'marketing'];
    $roles = ['Roles', route('roles.index'), 'roles.*', 'M12 15a4 4 0 100-8 4 4 0 000 8z M5.5 21a6.5 6.5 0 0113 0'];
    $permissions = ['Permissions', route('permissions.index'), 'permissions.*', 'M9 12l2 2 4-4 M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7z'];
    $profile = ['My Profile', route('profile.edit'), 'profile.*', 'M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2 2-.06-.06a1.7 1.7 0 00-1.88-.34'];
    $profileShort = ['Profile', route('profile.edit'), 'profile.*', 'M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2 2-.06-.06a1.7 1.7 0 00-1.88-.34'];
    // Laboratory Daily Operations & Maintenance — one nav entry linking to
    // its standalone dashboard, active-matched across every lab* route name
    // (same "one entry, several sub-resources" shape as Dispatch Planning).
    $labOps = ['Lab Operations', route('lab.dashboard'), 'lab.dashboard', 'M9 3v6l-5 9a2 2 0 002 3h12a2 2 0 002-3l-5-9V3M9 3h6', fn () => request()->routeIs(['lab.*', 'lab-equipment.*', 'lab-checklists.*', 'lab-maintenance.*', 'lab-media.*', 'lab-chemicals.*', 'lab-contamination.*', 'lab-reports.*'])];

    $items = match ($roleName) {
        'super-admin', 'admin' => [
            $dashboard, $users, $marketingUsers, $dealers, $dealerAssignments, $farmers, $states, $districts, $talukas, $villages,
            $bookings, $dispatchPlanning, $dispatch, $vehicles, $challans, $invoices, $payments, $ledger, $stock, $labOps, $reports,
            $roles, $permissions, $profile,
        ],
        // Marketing must NEVER see: Users, Roles, Permissions, Settings,
        // Reports, System Configuration, Other Dealers, Other Farmers.
        // Every item here is already query-scoped to this Marketing user's
        // own dealer assignments (DealerController/FarmerController/
        // BookingController/DispatchController filtered()). Dispatch
        // Planning is view-only for Marketing per the module's own spec.
        'marketing' => [$dashboard, $myDealers, $myFarmers, $bookings, $dispatchPlanning, $dispatch, $profileShort],
        // Dealer: "Documents" refers to the photo/signature already shown
        // on a Dispatch's own Documents tab — there is no separate
        // stand-alone Documents page/module to link to.
        'dealer' => [$dashboard, $myFarmers, $bookings, $dispatch, $profileShort],
        'accounts' => [$dashboard, $bookings, $payments, $challans, $dispatchView, $profile],
        // "Delivery" is the Mark Delivered action inside the Dispatch
        // module itself, not a separate page.
        'dispatch' => [$dashboard, $approvedBookings, $dispatch, $profile],
        // Dispatch Planner: builds the day's plan and groups bookings onto
        // vehicles — everything downstream (Dispatch itself) stays with the
        // Dispatch role.
        'dispatch-planner' => [$dashboard, $dispatchPlanning, $profileShort],
        // Supervisor's Dispatch Planning loading screens don't exist yet
        // (Phase 4 of that module) — no nav entry for it until there's
        // somewhere for it to go, since DispatchPlanPolicy is intentionally
        // planner/admin-only. Supervisor's own menu today is just the Lab
        // Ops approval queue.
        // Supervisor also drives a vehicle from Approve Loading through to
        // Dispatched (challan issued) — see DispatchPolicy::create()/
        // canOperate() — so they need to find their own dispatches again.
        'supervisor' => [$dashboard, $dispatch, $labOps, $profileShort],
        // Lab Technician: the employee-login role for the daily checklist
        // and the 4 operational logs — everything lives under one Lab
        // Operations entry/dashboard.
        'lab-technician' => [$dashboard, $labOps, $profileShort],
        // Any role with no defined menu (e.g. Staff, which is seeded with
        // zero permissions) gets the minimal safe default rather than
        // silently falling through to the full admin menu.
        default => [$dashboard, $profile],
    };
@endphp
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" @click="sidebarOpen = false"></div>
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-[280px] shrink-0 flex-col bg-slate-950 text-slate-100 shadow-2xl transition-transform duration-200 lg:static lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-800 px-5"><a href="{{ route('dashboard') }}" class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600 text-lg font-bold text-white">A</span><span><span class="block text-sm font-bold text-white">Arcadia Biotech</span><span class="block text-[10px] font-semibold uppercase tracking-[.16em] text-blue-400">Enterprise ERP</span></span></a><button type="button" @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" aria-label="Close navigation menu"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg></button></div>
    <nav id="sidebar-scroll" class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-3 py-5" aria-label="Primary navigation"><p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[.16em] text-slate-500">Workspace</p><div class="space-y-1">@foreach ($items as $item) @php($label = $item[0]) @php($href = $item[1]) @php($route = $item[2]) @php($path = $item[3]) @php($active = isset($item[4]) ? $item[4]() : request()->routeIs($route)) <a href="{{ $href }}" @click="sidebarOpen = false" @if($active) data-sidebar-active @endif @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition', 'bg-blue-600 text-white shadow-lg shadow-blue-950/40' => $active, 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! $active])><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" /></svg><span>{{ $label }}</span></a> @endforeach</div></nav>
    @php($unreadNotifications = $navUser?->unreadNotifications()->latest()->limit(8)->get() ?? collect())
    <div class="shrink-0 border-t border-slate-800 p-4" x-data="{ notifOpen: false }">
        <div class="relative mb-3">
            <button type="button" @click="notifOpen = ! notifOpen" class="flex w-full items-center justify-between rounded-lg p-2 text-slate-300 hover:bg-slate-800">
                <span class="flex items-center gap-2 text-sm font-medium"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>Notifications</span>
                @if($unreadNotifications->isNotEmpty())<span class="grid h-5 w-5 place-items-center rounded-full bg-rose-500 text-[10px] font-bold text-white">{{ $unreadNotifications->count() }}</span>@endif
            </button>
            <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false" class="absolute bottom-full left-0 mb-2 w-72 rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                @forelse($unreadNotifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="block">
                        @csrf
                        <button class="w-full rounded-lg p-2 text-left text-xs text-slate-300 hover:bg-slate-800">
                            <span class="block text-slate-100">{{ $notification->data['message'] ?? 'Notification' }}</span>
                            <span class="mt-1 block text-slate-500">{{ $notification->created_at->diffForHumans() }}</span>
                        </button>
                    </form>
                @empty
                    <p class="p-3 text-center text-xs text-slate-500">No new notifications.</p>
                @endforelse
            </div>
        </div>
        <a href="{{ route('profile.edit') }}" class="mb-3 flex items-center gap-3 rounded-lg p-2 hover:bg-slate-800"><span class="grid h-9 w-9 place-items-center rounded-full bg-slate-800 text-sm font-bold text-blue-300">{{ strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}</span><span class="min-w-0"><span class="block truncate text-sm font-semibold text-white">{{ auth()->user()->name ?? 'User' }}</span><span class="block truncate text-xs text-slate-500">{{ auth()->user()->email ?? '' }}</span></span></a><form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-700 px-3 py-2.5 text-sm font-semibold text-slate-300 hover:bg-rose-500/10 hover:text-rose-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 17l5-5-5-5M21 12H9m3 7v2H5a2 2 0 01-2-2V5a2 2 0 012-2h7v2" /></svg>Logout</button></form></div>
</aside>
