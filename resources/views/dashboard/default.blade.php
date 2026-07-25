<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome, {{ auth()->user()->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">Your account doesn't have a module assigned yet. Contact an administrator if you believe this is a mistake.</p>
            <a href="{{ route('profile.edit') }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">My Profile</a>
        </div>
    </div>
</x-app-layout>
