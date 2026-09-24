@extends('layouts.admin')

@section('title', 'تقارير الكهرباء')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-white sm:text-3xl">تقارير فحص الكهرباء</h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ $reports->total() }} تقرير
                @if ($search) — نتائج البحث @endif
            </p>
        </div>
        <a href="{{ route('admin.reports.create') }}"
            class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-400">
            + تقرير جديد
        </a>
    </div>

    <form method="get" class="mb-5 flex flex-col gap-2 sm:flex-row">
        <input type="search" name="search" value="{{ $search }}" placeholder="بحث بالعنوان أو العميل أو الرقم..."
            class="w-full flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none">
        <button type="submit" class="rounded-xl border border-slate-700 px-5 py-3 text-sm font-bold text-slate-200 hover:bg-slate-800">بحث</button>
        @if ($search)
            <a href="{{ route('admin.reports.index') }}" class="rounded-xl border border-slate-700 px-5 py-3 text-center text-sm font-bold text-slate-400 hover:bg-slate-800">مسح</a>
        @endif
    </form>

    @if ($reports->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-700 px-6 py-16 text-center">
            <p class="text-slate-400">لا توجد تقارير{{ $search ? ' مطابقة للبحث' : '' }}.</p>
            @unless ($search)
                <a href="{{ route('admin.reports.create') }}" class="mt-4 inline-block text-sm font-bold text-emerald-400 hover:text-emerald-300">+ أنشئ أول تقرير</a>
            @endunless
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold">العنوان</th>
                        <th class="hidden px-4 py-3 text-right font-semibold sm:table-cell">العميل</th>
                        <th class="px-4 py-3 text-right font-semibold">الرقم</th>
                        <th class="hidden px-4 py-3 text-right font-semibold sm:table-cell">الأقسام</th>
                        <th class="hidden px-4 py-3 text-right font-semibold md:table-cell">التاريخ</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950/40">
                    @foreach ($reports as $report)
                        <tr class="hover:bg-slate-900/60">
                            <td class="px-4 py-3 font-bold text-white">
                                <a href="{{ route('admin.reports.show', $report) }}" class="hover:text-emerald-400">{{ $report->title }}</a>
                            </td>
                            <td class="hidden px-4 py-3 text-slate-300 sm:table-cell">{{ $report->client_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-emerald-400">{{ $report->reportNumber() }}</td>
                            <td class="hidden px-4 py-3 text-slate-300 sm:table-cell">{{ $report->sections_count }}</td>
                            <td class="hidden px-4 py-3 text-slate-400 md:table-cell">{{ optional($report->inspection_date)->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3 text-left whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('admin.reports.show', $report) }}" class="font-bold text-emerald-400 hover:text-emerald-300">فتح</a>
                                    <form method="post" action="{{ route('admin.reports.destroy', $report) }}" class="inline"
                                        @submit.prevent="$store.adminUi.askConfirm(@js('حذف التقرير «'.$report->title.'» بالكامل؟'), $el)">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-bold text-rose-400 hover:text-rose-300">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $reports->onEachSide(1)->links() }}
        </div>
    @endif
@endsection
