@extends('layouts.admin')

@section('title', 'الوصف والتعليقات')

@section('content')
@php $tab = request('tab', 'descriptions'); @endphp
<div x-data="{ tab: @js($tab) }">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-white sm:text-3xl">الوصف والتعليقات الجاهزة</h1>
            <p class="mt-1 text-sm text-slate-400">أضف نصوصاً جاهزة تظهر لك عند تعبئة أقسام التقارير لاختيارها بسرعة.</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300">← التقارير</a>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-1 rounded-2xl border border-slate-800 bg-slate-900/50 p-1 text-center text-sm font-bold">
        <button type="button" @click="tab = 'descriptions'"
            class="rounded-xl px-4 py-2.5 transition"
            :class="tab === 'descriptions' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-white'">الأوصاف</button>
        <button type="button" @click="tab = 'comments'"
            class="rounded-xl px-4 py-2.5 transition"
            :class="tab === 'comments' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-white'">التعليقات</button>
    </div>

    {{-- Descriptions --}}
    <div x-show="tab === 'descriptions'" class="space-y-5">
        <form method="post" action="{{ route('admin.presets.store') }}" class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4">
            @csrf
            <input type="hidden" name="type" value="description">
            <label class="mb-2 block text-sm font-medium text-slate-300">وصف جديد</label>
            <textarea name="body" rows="3" required
                class="mb-3 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none"
                placeholder="مثال: تقرير لوحة التوزيع في الطابق الأرضي"></textarea>
            <button class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400">+ إضافة وصف</button>
        </form>

        <div class="space-y-2">
            @forelse ($descriptions as $item)
                <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4" x-data="{ editing: false }">
                    <div x-show="!editing" class="flex items-start gap-3">
                        <p class="flex-1 text-sm leading-relaxed text-slate-200">{{ $item->body }}</p>
                        <button type="button" @click="editing = true" class="text-xs font-bold text-emerald-400">تعديل</button>
                        <form method="post" action="{{ route('admin.presets.destroy', $item) }}"
                            @submit.prevent="$store.adminUi.askConfirm('حذف هذا الوصف؟', $el)">
                            @csrf @method('DELETE')
                            <button class="text-xs font-bold text-rose-400">حذف</button>
                        </form>
                    </div>
                    <form x-show="editing" x-cloak method="post" action="{{ route('admin.presets.update', $item) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <textarea name="body" rows="3" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">{{ $item->body }}</textarea>
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-bold text-slate-950">حفظ</button>
                            <button type="button" @click="editing = false" class="rounded-lg border border-slate-700 px-4 py-2 text-xs text-slate-300">إلغاء</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-700 px-6 py-12 text-center text-slate-500">
                    لا توجد أوصاف بعد — أضف أول وصف من النموذج أعلاه.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Comments --}}
    <div x-show="tab === 'comments'" x-cloak class="space-y-5">
        <form method="post" action="{{ route('admin.presets.store') }}" class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4">
            @csrf
            <input type="hidden" name="type" value="comment">
            <label class="mb-2 block text-sm font-medium text-slate-300">تعليق جديد</label>
            <textarea name="body" rows="3" required
                class="mb-3 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none"
                placeholder="مثال: يفضل عمل صيانة دورية بواسطة كهربائي معتمد"></textarea>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="text-xs text-slate-400">اللون الافتراضي:</span>
                <label class="flex items-center gap-1.5 text-xs text-slate-300">
                    <input type="radio" name="color" value="none" checked class="accent-slate-400"> بدون
                </label>
                <label class="flex items-center gap-1.5 text-xs text-slate-300">
                    <input type="radio" name="color" value="red" class="accent-red-500"> أحمر
                </label>
                <label class="flex items-center gap-1.5 text-xs text-slate-300">
                    <input type="radio" name="color" value="green" class="accent-emerald-500"> أخضر
                </label>
            </div>
            <button class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400">+ إضافة تعليق</button>
        </form>

        <div class="space-y-2">
            @forelse ($comments as $item)
                <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4" x-data="{ editing: false }">
                    <div x-show="!editing" class="flex items-start gap-3">
                        <div class="flex-1">
                            <p class="text-sm leading-relaxed text-slate-200
                                {{ $item->color === 'red' ? 'bg-red-500/20' : ($item->color === 'green' ? 'bg-emerald-500/20' : '') }}
                                inline rounded px-1">{{ $item->body }}</p>
                            <p class="mt-1 text-[10px] text-slate-500">
                                @if ($item->color === 'red') أحمر
                                @elseif ($item->color === 'green') أخضر
                                @else بدون لون @endif
                            </p>
                        </div>
                        <button type="button" @click="editing = true" class="text-xs font-bold text-emerald-400">تعديل</button>
                        <form method="post" action="{{ route('admin.presets.destroy', $item) }}"
                            @submit.prevent="$store.adminUi.askConfirm('حذف هذا التعليق؟', $el)">
                            @csrf @method('DELETE')
                            <button class="text-xs font-bold text-rose-400">حذف</button>
                        </form>
                    </div>
                    <form x-show="editing" x-cloak method="post" action="{{ route('admin.presets.update', $item) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <textarea name="body" rows="3" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">{{ $item->body }}</textarea>
                        <div class="flex flex-wrap gap-3 text-xs text-slate-300">
                            <label><input type="radio" name="color" value="none" @checked($item->color === 'none')> بدون</label>
                            <label><input type="radio" name="color" value="red" @checked($item->color === 'red')> أحمر</label>
                            <label><input type="radio" name="color" value="green" @checked($item->color === 'green')> أخضر</label>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-bold text-slate-950">حفظ</button>
                            <button type="button" @click="editing = false" class="rounded-lg border border-slate-700 px-4 py-2 text-xs text-slate-300">إلغاء</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-700 px-6 py-12 text-center text-slate-500">
                    لا توجد تعليقات بعد — أضف أول تعليق من النموذج أعلاه.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
