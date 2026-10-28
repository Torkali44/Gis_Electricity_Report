<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Production must ship public/build — CDN fallback is CSS-only and breaks Excel/presets/annotate --}}
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
        @if (app()->environment('production'))
            <meta name="gis-missing-vite-build" content="1">
        @endif
    @endif

    <style>
        body {
            font-family: 'IBM Plex Sans Arabic', 'Outfit', sans-serif;
            background: #020617;
            color: #f1f5f9;
            margin: 0;
        }
        [x-cloak] { display: none !important; }
    </style>
    @stack('styles')
</head>

<body
    class="min-h-full antialiased bg-slate-950"
    data-admin-page
    x-data="{ navOpen: false }"
    @keydown.escape.window="
        if ($store.adminUi.confirmOpen) $store.adminUi.cancelConfirm();
        else if ($store.adminUi.toastOpen) $store.adminUi.closeToast();
    "
    @admin-toast.window="$store.adminUi.showToast($event.detail.message, $event.detail.type || 'success')"
    x-init="
        @if (session('status'))
            $store.adminUi.showToast(@js(session('status')), 'success');
        @endif
        @if (isset($errors) && $errors->any())
            $store.adminUi.showToast(@js($errors->first()), 'error', 5000);
        @endif
    "
>
    <header class="sticky top-0 z-40 border-b border-white/5 bg-slate-950/90 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-2 text-lg font-bold text-white sm:text-xl">
                <img src="{{ asset('images/newLog.jpeg') }}" alt="GIS Logo" class="h-11 w-auto max-w-[140px] rounded-lg object-contain bg-white p-0.5">
                <span><span class="text-emerald-500">GIS</span> <span class="hidden xs:inline sm:inline">تقارير الكهرباء</span></span>
            </a>

            <button type="button" class="rounded-lg border border-slate-700 p-2 text-slate-300 md:hidden" @click="navOpen = !navOpen" aria-label="القائمة">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <nav class="hidden items-center gap-3 md:flex">
                <a href="{{ route('admin.reports.index') }}"
                    class="text-sm font-semibold {{ request()->routeIs('admin.reports.*') ? 'text-emerald-400' : 'text-slate-400 hover:text-white' }}">التقارير</a>
                <a href="{{ route('admin.reports.create') }}" class="text-sm font-semibold text-slate-400 hover:text-white">تقرير جديد</a>
                <a href="{{ route('admin.presets.index') }}"
                    class="text-sm font-semibold {{ request()->routeIs('admin.presets.*') ? 'text-emerald-400' : 'text-slate-400 hover:text-white' }}">الوصف والتعليقات</a>
                <a href="{{ route('home') }}" class="text-sm font-semibold text-slate-400 hover:text-white">الموقع</a>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700">خروج</button>
                </form>
            </nav>
        </div>

        <div x-show="navOpen" x-cloak class="border-t border-slate-800 px-4 py-3 md:hidden">
            <div class="flex flex-col gap-2">
                <a href="{{ route('admin.reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">التقارير</a>
                <a href="{{ route('admin.reports.create') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">تقرير جديد</a>
                <a href="{{ route('admin.presets.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">الوصف والتعليقات</a>
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">الموقع العام</a>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="w-full rounded-lg bg-slate-800 px-3 py-2 text-sm font-bold text-white">تسجيل الخروج</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    {{-- Toast popup --}}
    <div
        x-show="$store.adminUi.toastOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-x-0 top-4 z-[70] flex justify-center px-4 pointer-events-none"
        role="status"
    >
        <div
            class="pointer-events-auto flex max-w-md items-start gap-3 rounded-2xl border px-4 py-3 shadow-2xl"
            :class="{
                'border-emerald-500/40 bg-slate-900 text-emerald-200': $store.adminUi.toastType === 'success',
                'border-rose-500/40 bg-slate-900 text-rose-200': $store.adminUi.toastType === 'error',
                'border-amber-500/40 bg-slate-900 text-amber-200': $store.adminUi.toastType === 'warning',
            }"
        >
            <p class="flex-1 text-sm font-bold leading-relaxed" x-text="$store.adminUi.toastMessage"></p>
            <button type="button" class="rounded-lg p-1 opacity-70 hover:opacity-100" @click="$store.adminUi.closeToast()" aria-label="إغلاق">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Confirm popup --}}
    <div
        x-show="$store.adminUi.confirmOpen"
        x-cloak
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="admin-confirm-title"
    >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="$store.adminUi.cancelConfirm()"></div>
        <div
            class="relative z-10 w-full max-w-sm rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.stop
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-500/15 text-rose-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <h2 id="admin-confirm-title" class="text-center text-lg font-bold text-white">تأكيد العملية</h2>
            <p class="mt-2 text-center text-sm leading-relaxed text-slate-300" x-text="$store.adminUi.confirmMessage"></p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button"
                    class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-bold text-slate-200 hover:bg-slate-800"
                    @click="$store.adminUi.cancelConfirm()">
                    إلغاء
                </button>
                <button type="button"
                    class="rounded-xl bg-rose-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-400"
                    @click="$store.adminUi.acceptConfirm()">
                    تأكيد الحذف
                </button>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
