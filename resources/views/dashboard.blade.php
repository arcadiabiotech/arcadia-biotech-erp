<x-app-layout>

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                Arcadia Biotech ERP Dashboard
            </h2>

            <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full font-semibold">
                ● System Online
            </span>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto py-8 px-6">

        <h1 class="text-3xl font-bold text-gray-800">
            Welcome, {{ Auth::user()->name }}
        </h1>

        <p class="text-gray-500 mb-8">
            Banana Tissue Culture ERP Management System
        </p>

        <!-- Dashboard Cards -->

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <div class="bg-blue-600 rounded-xl shadow-lg p-6 text-white">
                <p class="text-lg">👨‍💼 Dealers</p>
                <h2 class="text-4xl font-bold mt-3">0</h2>
            </div>

            <div class="bg-green-600 rounded-xl shadow-lg p-6 text-white">
                <p class="text-lg">👨‍🌾 Farmers</p>
                <h2 class="text-4xl font-bold mt-3">0</h2>
            </div>

            <div class="bg-yellow-500 rounded-xl shadow-lg p-6 text-white">
                <p class="text-lg">📑 Bookings</p>
                <h2 class="text-4xl font-bold mt-3">0</h2>
            </div>

            <div class="bg-red-600 rounded-xl shadow-lg p-6 text-white">
                <p class="text-lg">🚚 Dispatch</p>
                <h2 class="text-4xl font-bold mt-3">0</h2>
            </div>

        </div>

        <!-- Quick Menu & ERP Status -->

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Quick Menu -->

            <div class="bg-white rounded-xl shadow-lg p-6">

                <h2 class="text-2xl font-bold mb-6 text-gray-700">
                    Quick Menu
                </h2>

                <div class="grid grid-cols-2 gap-4">

                    <a href="{{ route('dealers.index') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white text-center py-4 rounded-lg font-semibold shadow">
                        👨‍💼 Dealers
                    </a>

                    <a href="{{ route('customers.index') }}"
                       class="bg-green-600 hover:bg-green-700 text-white text-center py-4 rounded-lg font-semibold shadow">
                        👨‍🌾 Farmers
                    </a>

                    <a href="#"
                       class="bg-yellow-500 hover:bg-yellow-600 text-white text-center py-4 rounded-lg font-semibold shadow">
                        📑 Booking
                    </a>

                    <a href="#"
                       class="bg-red-600 hover:bg-red-700 text-white text-center py-4 rounded-lg font-semibold shadow">
                        🚚 Dispatch
                    </a>

                    <a href="#"
                       class="bg-purple-600 hover:bg-purple-700 text-white text-center py-4 rounded-lg font-semibold shadow">
                        💰 Payment
                    </a>

                    <a href="#"
                       class="bg-gray-800 hover:bg-gray-900 text-white text-center py-4 rounded-lg font-semibold shadow">
                        📦 Stock
                    </a>

                </div>

            </div>

            <!-- ERP Status -->

            <div class="bg-white rounded-xl shadow-lg p-6">

                <h2 class="text-2xl font-bold mb-6 text-gray-700">
                    ERP Status
                </h2>

                <table class="w-full">

                    <tr class="border-b">
                        <td class="py-3 font-semibold">System</td>
                        <td class="text-green-600 font-bold">Running</td>
                    </tr>

                    <tr class="border-b">
                        <td class="py-3 font-semibold">Database</td>
                        <td class="text-green-600 font-bold">Connected</td>
                    </tr>

                    <tr class="border-b">
                        <td class="py-3 font-semibold">Logged User</td>
                        <td>{{ Auth::user()->name }}</td>
                    </tr>

                    <tr class="border-b">
                        <td class="py-3 font-semibold">Role</td>
                        <td>Administrator</td>
                    </tr>

                    <tr>
                        <td class="py-3 font-semibold">Version</td>
                        <td>Arcadia ERP v1.0</td>
                    </tr>

                </table>

            </div>

        </div>

    </div>

</x-app-layout>