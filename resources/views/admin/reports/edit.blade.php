@extends('layouts.admin')

@section('title', 'تعديل التقرير')

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.reports.show', $report) }}" class="text-sm font-semibold text-slate-400 hover:text-emerald-400">← رجوع للتقرير</a>
        <h1 class="mt-3 text-3xl font-black text-white">تعديل بيانات التقرير</h1>
    </div>

    <form method="post" action="{{ route('admin.reports.update', $report) }}" class="max-w-3xl space-y-6 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
        @csrf
        @method('PUT')
        @include('admin.reports.partials.meta-fields', ['report' => $report])
        <button type="submit" class="rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 hover:bg-emerald-400">حفظ التعديلات</button>
    </form>
@endsection
