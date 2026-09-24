@extends('layouts.guest')

@section('title', 'انتهت الجلسة')

@section('content')
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
        <!-- Background Blur -->
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-[120px]"></div>
        <div class="absolute -right-20 bottom-0 h-96 w-96 rounded-full bg-amber-500/10 blur-[120px]"></div>

        <div class="glass-card relative z-10 w-full max-w-md rounded-3xl p-8 lg:p-10 shadow-2xl text-center">
            <!-- Icon -->
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 shadow-xl shadow-amber-500/10">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Error Code & Title -->
            <span class="inline-block rounded-full bg-slate-800/80 px-3 py-1 text-xs font-bold tracking-wider text-amber-400 border border-slate-700 mb-3">خطأ 419</span>
            <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-white mb-3">انتهت صلاحية الجلسة</h1>
            
            <p class="text-slate-400 mb-8 text-sm leading-relaxed">
                انتهت صلاحية الجلسة الحالية بسبب عدم النشاط لفترة طويلة أو انتهاء رمز الأمان. يرجى تسجيل الدخول مجدداً لمتابعة عملك.
            </p>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <a
                    href="{{ route('login') }}"
                    class="group relative flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-xl bg-emerald-500 py-3.5 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 hover:shadow-emerald-500/40 active:scale-[0.98]"
                >
                    <svg class="h-5 w-5 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <span>تسجيل الدخول مجدداً</span>
                </a>

                <a
                    href="{{ route('home') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700/80 bg-slate-900/60 py-3 text-sm font-semibold text-slate-300 transition-all hover:border-slate-600 hover:bg-slate-800 hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>العودة للصفحة الرئيسية</span>
                </a>
            </div>
        </div>
    </div>
@endsection
