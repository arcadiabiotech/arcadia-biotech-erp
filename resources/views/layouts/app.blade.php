<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Arcadia Biotech ERP</title>

    @vite(['resources/css/app.css','resources/js/app.js'])

</head>

<body class="bg-slate-100 font-sans antialiased">

<div class="flex min-h-screen">

    {{-- Sidebar --}}
    @include('layouts.navigation')

    {{-- Main Content --}}
    <div class="flex-1 ml-64">

        {{-- Top Header --}}
        <header class="bg-white h-16 shadow flex items-center justify-between px-8">

            <div>

                <h1 class="text-2xl font-bold text-slate-800">

                    Arcadia Biotech ERP

                </h1>

                <p class="text-sm text-slate-500">

                    Banana Tissue Culture Management System

                </p>

            </div>

            <div class="flex items-center gap-5">

                <input
                    type="text"
                    placeholder="Search..."
                    class="border rounded-lg px-4 py-2 w-72">

                <div class="text-sm">

                    {{ Auth::user()->name }}

                </div>

            </div>

        </header>

        {{-- Page Header --}}
        @isset($header)

        <div class="px-8 py-5">

            {{ $header }}

        </div>

        @endisset

        {{-- Content --}}

        <main class="p-8">

            @if(isset($slot))

                {{ $slot }}

            @endif

            @yield('content')

        </main>

    </div>

</div>

</body>
</html>