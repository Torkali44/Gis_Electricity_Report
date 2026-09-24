@extends('layouts.guest')

@section('title', 'الرئيسية')

@section('content')
    <div class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('images/hero.jpg') }}" class="h-full w-full object-cover" alt="فحص كهرباء">
            <div class="absolute inset-0 bg-gradient-to-l from-slate-950 via-slate-950/80 to-transparent"></div>
            <div class="absolute inset-0 bg-slate-950/40"></div>
        </div>

        <nav class="relative z-50 flex items-center justify-between px-6 py-8 lg:px-12">
            <div class="flex items-center gap-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500 shadow-lg shadow-emerald-500/30">
                    <svg class="h-6 w-6 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-white"><span class="text-emerald-500">GIS</span> تقارير الكهرباء</span>
            </div>
            <a href="{{ route('login') }}" class="rounded-full bg-white/10 px-6 py-2.5 text-sm font-semibold text-white backdrop-blur-md transition hover:bg-white/20">
                دخول المسؤول
            </a>
        </nav>

        <div class="relative z-10 mx-auto flex min-h-[calc(100vh-100px)] max-w-7xl flex-col justify-center px-6 lg:px-12">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-widest text-emerald-400">نظام تقارير فحص الكهرباء — GIS</span>
                </div>

                <h1 class="text-5xl font-extrabold leading-[1.1] text-white sm:text-7xl">
                    تقارير <span class="gradient-text">فحص الكهرباء</span><br>احترافية PDF و Word
                </h1>

                <p class="mt-8 max-w-2xl text-lg leading-relaxed text-slate-400">
                    أنشئ أقسام الفحص مع الصور والوصف والتعليقات والجداول، وحدّد لكل قيمة اللون الأحمر أو الأخضر ليظهر مباشرة في التقرير النهائي.
                </p>

                <div class="mt-12 flex flex-wrap gap-5">
                    <a href="{{ route('login') }}" class="group flex items-center gap-3 rounded-2xl bg-emerald-500 px-8 py-4 font-bold text-slate-950 transition hover:bg-emerald-400 hover:shadow-[0_0_40px_rgba(16,185,129,0.3)]">
                        <span>دخول لوحة التحكم</span>
                        <svg class="h-5 w-5 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="mt-24 grid grid-cols-1 gap-6 sm:grid-cols-3 lg:max-w-4xl">
                <div class="glass-card rounded-2xl p-6 transition hover:-translate-y-1">
                    <h3 class="text-lg font-bold text-white">أقسام مرنة</h3>
                    <p class="mt-2 text-sm text-slate-500">اسم، صور، وصف، تعليقات ملونة — وجدول اختياري لكل قسم.</p>
                </div>
                <div class="glass-card rounded-2xl p-6 transition hover:-translate-y-1">
                    <h3 class="text-lg font-bold text-white">ألوان أحمر / أخضر</h3>
                    <p class="mt-2 text-sm text-slate-500">كل قيمة في الجداول والتعليقات بلون تختاره ويظهر في PDF و Word.</p>
                </div>
                <div class="glass-card rounded-2xl p-6 transition hover:-translate-y-1">
                    <h3 class="text-lg font-bold text-white">تصدير مزدوج</h3>
                    <p class="mt-2 text-sm text-slate-500">تحميل تقرير PDF وتقرير Word بنفس المحتوى والتنسيق.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
