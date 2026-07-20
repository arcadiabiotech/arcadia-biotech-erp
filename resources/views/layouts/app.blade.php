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
    <div x-data="{ sidebarOpen: false }" class="min-h-screen">
        @include('layouts.navigation')

        <div class="min-h-screen lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6 lg:px-8">
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
                <div class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">{{ $header }}</div>
            @endisset
            <main class="p-4 sm:p-6 lg:p-8">
                @if (isset($slot))
                    {{ $slot }}
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
