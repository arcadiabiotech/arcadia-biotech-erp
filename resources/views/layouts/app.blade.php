<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Arcadia Biotech ERP') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 font-sans antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">
        @include('layouts.navigation')

        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <header class="z-10 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" @click="sidebarOpen = true" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 lg:hidden" aria-label="Open navigation menu">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-slate-900 sm:text-lg">Arcadia Biotech ERP</p>
                        <p class="hidden text-xs text-slate-500 sm:block">Banana Tissue Culture Management System</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <div class="hidden items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 md:flex"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>System online</div>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg p-1.5 transition hover:bg-slate-100"><span class="grid h-8 w-8 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white">{{ strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}</span><span class="hidden text-left sm:block"><span class="block max-w-32 truncate text-sm font-semibold text-slate-700">{{ auth()->user()->name ?? 'User' }}</span><span class="block text-xs text-slate-500">My account</span></span></a>
                </div>
            </header>
            @isset($header)
                <div class="shrink-0 border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">{{ $header }}</div>
            @endisset
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                @if (isset($slot))
                    {{ $slot }}
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
        // Auto-capitalizes the first letter of any "name"-type field as the
        // user types (dealer_name, farmer_name, driver_name, chemical_name,
        // media_name, bank_name, helper_name, father_name, or a plain
        // "name" field) — mirrors the server-side CapitalizesNames trait,
        // which normalizes the same fields again on save regardless of what
        // reaches the server. Deliberately only the *first* character, not
        // full Title Case, so intentional casing further into the value
        // (e.g. "pH Meter") survives untouched. Only matches inputs whose
        // `name` attribute is exactly "name" or ends in "_name" — this
        // excludes "username" (no underscore before "name") and every
        // unrelated field, so it never touches Role/Permission slugs or
        // regular sentence fields like remarks/address/description.
        document.addEventListener('input', function (e) {
            var el = e.target;
            if (el.tagName !== 'INPUT' || (el.type !== 'text' && el.type !== '')) return;
            if (! /(^|_)name$/.test(el.name)) return;

            var value = el.value;
            if (! value || /^[A-Z]/.test(value.charAt(0))) return;

            var pos = el.selectionStart;
            el.value = value.charAt(0).toUpperCase() + value.slice(1);
            el.setSelectionRange(pos, pos);
        });
    </script>
    <script>
        // Used by forms embedding the otp-mobile-gate component (Marketing
        // User / Dealer / Farmer creation) to block submission client-side until
        // the mobile has been OTP-verified. This is a UX courtesy only —
        // the real enforcement is server-side (OtpService::isVerified()
        // checked in the controller's store()), never trust this alone.
        window.arcadiaCheckOtpVerified = function (form, hiddenFieldId) {
            const flag = form.querySelector('#' + hiddenFieldId);
            if (! flag || flag.value !== '1') {
                alert('Please verify the mobile number via OTP before submitting.');
                return false;
            }
            return true;
        };

        // Used by every Lab Ops "Supervisor decision" approve form (the
        // lab-signature-pad component) to block submission when nothing
        // was signed — the server also requires signature_data, but without
        // this check the form just silently redirects back with a
        // validation error, which reads as "the button did nothing".
        window.arcadiaCheckSignaturePresent = function (form, hiddenFieldId) {
            const flag = form.querySelector('#' + hiddenFieldId);
            if (! flag || ! flag.value) {
                alert('Please sign in the box above to confirm this decision before submitting.');
                return false;
            }
            return true;
        };

        // Same as above, but only enforced when the selected role option's
        // data-role-name attribute matches requiredRoleName — used by the
        // Users form, where OTP is only mandatory when the role being
        // created is Marketing.
        window.arcadiaCheckOtpVerifiedForRole = function (form, hiddenFieldId, roleSelectName, requiredRoleName) {
            const select = form.querySelector('[name=' + roleSelectName + ']');
            const selected = select ? select.options[select.selectedIndex] : null;
            const roleName = selected ? selected.dataset.roleName : null;
            if (roleName !== requiredRoleName) {
                return true;
            }
            return window.arcadiaCheckOtpVerified(form, hiddenFieldId);
        };
    </script>
    <script>
        // ── Sidebar scroll-position persistence ──────────────────────────
        // Every navigation in this app is a full page reload (no
        // Livewire/Turbo/Inertia here), so the sidebar's own scroll
        // position would otherwise reset to the top on every click.
        // sessionStorage is used (not localStorage) so the position is
        // just a per-tab UI nicety — it doesn't leak across browser
        // restarts or between different users on a shared machine.
        (function () {
            var STORAGE_KEY = 'arcadia:sidebarScrollTop';

            function getSidebar() {
                return document.getElementById('sidebar-scroll');
            }

            // Persist the sidebar's current scroll offset.
            function saveSidebarScroll() {
                var sidebar = getSidebar();
                if (!sidebar) return;
                sessionStorage.setItem(STORAGE_KEY, String(sidebar.scrollTop));
            }

            // Restore the sidebar's scroll offset, then make sure the
            // active menu item is visible regardless (covers the very
            // first visit, or a role switch that changes the menu itself
            // so the saved pixel offset no longer lines up with anything).
            function restoreSidebarScroll() {
                var sidebar = getSidebar();
                if (!sidebar) return;

                var saved = sessionStorage.getItem(STORAGE_KEY);
                if (saved !== null) {
                    // Direct property assignment is always instant — the
                    // `scroll-smooth` class only affects scrollTo()/
                    // scrollIntoView(), not a plain scrollTop set.
                    sidebar.scrollTop = parseInt(saved, 10) || 0;
                }

                var activeItem = sidebar.querySelector('[data-sidebar-active]');
                if (activeItem) {
                    activeItem.scrollIntoView({ block: 'nearest', behavior: 'instant' });
                }
            }

            // Save continuously while the user scrolls, not only on
            // unload — some mobile browsers skip beforeunload on certain
            // navigations (e.g. iOS Safari swipe-back), so relying on it
            // alone can silently drop the last scroll position.
            document.addEventListener('DOMContentLoaded', function () {
                var sidebar = getSidebar();
                if (sidebar) {
                    sidebar.addEventListener('scroll', saveSidebarScroll, { passive: true });
                }
            });

            // Final save right before leaving the page.
            window.addEventListener('beforeunload', saveSidebarScroll);

            // Restore as early as possible on every normal full-page load.
            document.addEventListener('DOMContentLoaded', restoreSidebarScroll);

            // `pageshow` also fires when a page is served from the
            // back/forward cache (bfcache) in Firefox/Safari, where
            // DOMContentLoaded does not fire again.
            window.addEventListener('pageshow', restoreSidebarScroll);

            // No-op safety hooks: neither Livewire nor Turbo is used in
            // this app today, but both swap page content without a full
            // reload, so if either is introduced later, scroll restoration
            // needs to re-run on their "navigation finished" event too.
            document.addEventListener('turbo:load', restoreSidebarScroll);
            document.addEventListener('livewire:navigated', restoreSidebarScroll);
        })();
    </script>
</body>
</html>
