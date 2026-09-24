@extends('layouts.admin')

@section('title', 'تقرير جديد')

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-slate-400 hover:text-emerald-400">← كل التقارير</a>
        <h1 class="mt-3 text-3xl font-black text-white">إنشاء تقرير كهرباء</h1>
    </div>

    <form method="post" action="{{ route('admin.reports.store') }}" class="max-w-3xl space-y-6 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
        @csrf
        @include('admin.reports.partials.meta-fields')
        <button type="submit" class="rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 hover:bg-emerald-400">حفظ ومتابعة الأقسام</button>
    </form>
@endsection
