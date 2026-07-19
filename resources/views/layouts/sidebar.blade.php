<aside
    x-data="{ open: true }"
    class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 text-white shadow-xl">

    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-slate-700">

        <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-xl">
            A
        </div>

        <div class="ml-3">

            <h2 class="text-lg font-bold">
                Arcadia ERP
            </h2>

            <p class="text-xs text-slate-400">
                Banana Tissue Culture
            </p>

        </div>

    </div>

    <!-- Menu -->

    <nav class="mt-5 px-4 space-y-2">

        <a href="{{ route('dashboard') }}"
            class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-800 transition">

            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8"/>

            </svg>

            Dashboard

        </a>

        <a href="{{ route('dealers.index') }}"
            class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-800 transition">

            <svg class="w-5 h-5 mr-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5V4H2v16h5"/>

            </svg>

            Dealers

        </a>

        <a href="{{ route('customers.index') }}"
            class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-800 transition">

            <svg class="w-5 h-5 mr-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0"/>

            </svg>

            Farmers

        </a>

        <hr class="border-slate-700">

        <p class="px-4 text-xs uppercase text-slate-500">
            Masters
        </p>