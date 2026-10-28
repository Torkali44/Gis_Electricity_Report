@extends('layouts.admin')

@section('title', $report->title)

@section('content')
<div
    x-data="sectionAnnotate"
    @annotate-image.window="openEditor({
        url: $event.detail.url,
        path: $event.detail.path,
        action: (@js(route('admin.reports.sections.image', [$report, 0]))).replace(/\/0\/image$/, '/' + $event.detail.sectionId + '/image')
    })"
>
<div
    x-data="reportShowPage({
        storeUrl: @js(route('admin.reports.sections.store', $report)),
        editMap: @js($report->sections->mapWithKeys(fn ($s) => [$s->id => $s->toEditorPayload()])),
        presetDescriptions: @js($presetDescriptions ?? []),
        presetComments: @js($presetComments ?? []),
    })"
    @keydown.escape.window="if (open) closeModal()"
>
    {{-- Top bar --}}
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-slate-400 hover:text-emerald-400">← كل التقارير</a>
            <h1 class="mt-2 truncate text-2xl font-black text-white sm:text-3xl">{{ $report->title }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                <span class="font-bold text-emerald-400">{{ $report->reportNumber() }}</span>
                @if ($report->client_name) · {{ $report->client_name }} @endif
                @if ($report->inspection_date) · {{ $report->inspection_date->format('Y-m-d') }} @endif
                · {{ $report->sections->count() }} قسم
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reports.edit', $report) }}"
                class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-bold text-slate-300 hover:bg-slate-800">بيانات التقرير</a>
            <a href="{{ route('admin.reports.pdf', ['report' => $report, 'inline' => 1]) }}" target="_blank"
                class="rounded-lg bg-emerald-500/15 px-3 py-2 text-sm font-bold text-emerald-400 hover:bg-emerald-500 hover:text-slate-950">عرض PDF</a>
            <a href="{{ route('admin.reports.pdf', $report) }}"
                class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400">PDF</a>
            <a href="{{ route('admin.reports.word', $report) }}"
                class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-500">Word</a>
            <button type="button" @click="openCreate()"
                class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-300">
                + قسم جديد
            </button>
        </div>
    </div>

    {{-- Sections grid --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($report->sections as $section)
            <article class="flex flex-col rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="truncate font-bold text-white">{{ $section->name }}</h3>
                        @if ($section->description)
                            <p class="mt-1 line-clamp-2 text-xs text-slate-400">{{ $section->description }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <form method="post" action="{{ route('admin.reports.sections.reorder', [$report, $section]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="up">
                            <button class="rounded border border-slate-700 px-1.5 py-0.5 text-xs text-slate-400 hover:text-white" title="أعلى">↑</button>
                        </form>
                        <form method="post" action="{{ route('admin.reports.sections.reorder', [$report, $section]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="down">
                            <button class="rounded border border-slate-700 px-1.5 py-0.5 text-xs text-slate-400 hover:text-white" title="أسفل">↓</button>
                        </form>
                    </div>
                </div>

                @php $sectionImages = $section->imageEntries(); @endphp
                @if ($sectionImages !== [])
                    <div class="mb-3 flex gap-1.5 overflow-x-auto pb-1">
                        @foreach ($sectionImages as $img)
                            <div class="relative shrink-0">
                                <img src="{{ $img['url'] }}" alt="" class="h-16 w-20 rounded-lg border border-slate-700 object-contain bg-slate-950">
                                <button type="button"
                                    class="absolute inset-x-0 bottom-0 bg-black/70 text-[9px] font-bold text-emerald-300"
                                    @click="$dispatch('annotate-image', { url: @js($img['url']), path: @js($img['path']), sectionId: {{ $section->id }} })">
                                    سهم
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mb-3 flex flex-wrap gap-1.5 text-[10px]">
                    @if ($section->has_table)
                        <span class="rounded bg-emerald-500/15 px-2 py-0.5 font-bold text-emerald-400">
                            {{ $section->table_type === 'mcb_panel' ? 'جدول MCB' : 'جدول تأريض' }}
                        </span>
                    @endif
                    <span class="rounded bg-slate-800 px-2 py-0.5 text-slate-400">{{ count($section->commentsList()) }} تعليق</span>
                    <span class="rounded bg-slate-800 px-2 py-0.5 text-slate-400">{{ count($section->images_json ?? []) }} صورة</span>
                </div>

                <div class="mt-auto flex gap-2 border-t border-slate-800 pt-3">
                    <button type="button"
                        @click="openEditById({{ $section->id }})"
                        class="flex-1 rounded-lg border border-emerald-500/40 py-2 text-xs font-bold text-emerald-400 hover:bg-emerald-500/10">
                        تعديل
                    </button>
                    <form method="post" action="{{ route('admin.reports.sections.destroy', [$report, $section]) }}" class="flex-1"
                        @submit.prevent="$store.adminUi.askConfirm('حذف القسم؟', $el)">
                        @csrf @method('DELETE')
                        <button class="w-full rounded-lg border border-rose-500/40 py-2 text-xs font-bold text-rose-400 hover:bg-rose-500/10">حذف</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-700 px-6 py-16 text-center">
                <p class="text-slate-500">لا توجد أقسام بعد — ابدأ بإضافة أول قسم من الزر أعلاه.</p>
                <button type="button" @click="openCreate()" class="mt-4 text-sm font-bold text-emerald-400">+ إضافة قسم</button>
            </div>
        @endforelse
    </div>

    <form method="post" action="{{ route('admin.reports.destroy', $report) }}" class="mt-10"
        @submit.prevent="$store.adminUi.askConfirm('حذف التقرير بالكامل؟', $el)">
        @csrf @method('DELETE')
        <button class="text-sm text-rose-400/70 hover:text-rose-300">حذف التقرير بالكامل</button>
    </form>

    {{-- ===================== SECTION MODAL ===================== --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeModal()"></div>

        <div
            class="relative z-10 flex max-h-[95vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl border border-slate-700 bg-slate-900 shadow-2xl sm:mx-4 sm:rounded-3xl"
            @click.stop
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        >
            {{-- Modal header --}}
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <div>
                    <h2 class="text-lg font-bold text-white" x-text="editingId ? 'تعديل القسم' : 'إضافة قسم جديد'"></h2>
                    <p class="text-xs text-slate-500">خطوة <span x-text="step"></span> من 3</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="إغلاق">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Steps indicator --}}
            <div class="grid grid-cols-3 gap-1 border-b border-slate-800 bg-slate-950/50 px-5 py-3 text-center text-xs font-bold">
                <button type="button" @click="step = 1" :class="step === 1 ? 'text-emerald-400' : 'text-slate-500'">1. البيانات</button>
                <button type="button" @click="step = 2" :class="step === 2 ? 'text-emerald-400' : 'text-slate-500'">2. التعليقات</button>
                <button type="button" @click="step = 3" :class="step === 3 ? 'text-emerald-400' : 'text-slate-500'">3. الجدول</button>
            </div>

            <form method="post" :action="formAction" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="_method" value="PATCH" x-bind:disabled="!editingId">

                <div class="flex-1 overflow-y-auto px-5 py-5">
                    {{-- STEP 1 --}}
                    <div x-show="step === 1" class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">اسم القسم *</label>
                            <input name="name" x-model="name" required
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none"
                                placeholder="لوحة التوزيع في الطابق الأرضي">
                        </div>

                        {{-- Description — pick from ready presets page --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-sm font-medium text-slate-300">الوصف</label>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.presets.index', ['tab' => 'descriptions']) }}" target="_blank"
                                        class="text-[11px] font-semibold text-slate-400 hover:text-emerald-400">إدارة الأوصاف ↗</a>
                                    <button type="button" @click="showDescPicker = !showDescPicker"
                                        class="rounded-lg border border-slate-600 px-2 py-1 text-[11px] font-semibold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                        اختيار جاهز (<span x-text="presetDescriptions.length"></span>)
                                    </button>
                                </div>
                            </div>
                            <div x-show="showDescPicker" x-cloak class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 p-1.5">
                                <template x-if="!presetDescriptions.length">
                                    <p class="px-2 py-3 text-center text-xs text-slate-500">لا أوصاف جاهزة — أضفها من صفحة «الوصف والتعليقات»</p>
                                </template>
                                <template x-for="(p, i) in presetDescriptions" :key="'pd'+i">
                                    <button type="button" @click="pickDescription(p)"
                                        class="block w-full rounded-lg px-2 py-2 text-right text-xs leading-relaxed text-slate-200 hover:bg-emerald-500/15 hover:text-emerald-300"
                                        x-text="p"></button>
                                </template>
                            </div>
                            <textarea name="description" x-model="description" rows="2"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none"
                                placeholder="تقرير لوحة التوزيع..."></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">صور القسم</label>
                            <input type="file" name="images[]" multiple accept="image/*"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-300 file:me-3 file:rounded-lg file:border-0 file:bg-emerald-500 file:px-3 file:py-1 file:text-xs file:font-bold file:text-slate-950">
                            <template x-if="existingImages.length">
                                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <template x-for="img in existingImages" :key="img.path">
                                        <div class="relative overflow-hidden rounded-xl border border-slate-700"
                                            :class="isRemoved(img.path) ? 'opacity-40 ring-2 ring-rose-500' : ''">
                                            <img :src="img.url" class="h-28 w-full object-contain bg-slate-950" alt="">
                                            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-1 bg-black/80 px-1.5 py-1">
                                                <label class="flex items-center gap-1 text-[10px] text-rose-200">
                                                    <input type="checkbox" name="remove_images[]" :value="img.path"
                                                        @change="toggleRemoveImage(img.path)" :checked="isRemoved(img.path)">
                                                    حذف
                                                </label>
                                                <button type="button"
                                                    class="rounded bg-emerald-500/90 px-1.5 py-0.5 text-[10px] font-bold text-slate-950"
                                                    @click="$dispatch('annotate-image', { url: img.url, path: img.path, sectionId: editingId })">
                                                    سهم
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- STEP 2 --}}
                    <div x-show="step === 2" class="space-y-3"
                        @add-comment-from-preset.window="comments.push({ text: $event.detail.text || '', color: $event.detail.color || 'none' })">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm text-slate-300">أضف تعليقات واختر لون كل تعليق (أحمر = يحتاج إصلاح).</p>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.presets.index', ['tab' => 'comments']) }}" target="_blank"
                                    class="text-[11px] font-semibold text-slate-400 hover:text-emerald-400">إدارة التعليقات ↗</a>
                                <button type="button" @click="showCommentPicker = !showCommentPicker"
                                    class="rounded-lg border border-slate-600 px-2 py-1 text-[11px] font-semibold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                    اختيار جاهز (<span x-text="presetComments.length"></span>)
                                </button>
                                <button type="button" @click="addComment()" class="text-xs font-bold text-emerald-400">+ تعليق</button>
                            </div>
                        </div>

                        <div x-show="showCommentPicker" x-cloak class="max-h-44 space-y-1 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 p-1.5">
                            <template x-if="!presetComments.length">
                                <p class="px-2 py-3 text-center text-xs text-slate-500">لا تعليقات جاهزة — أضفها من صفحة «الوصف والتعليقات»</p>
                            </template>
                            <template x-for="(p, i) in presetComments" :key="'pc'+i">
                                <button type="button"
                                    @click="pickComment(p)"
                                    class="block w-full rounded-lg px-2 py-2 text-right text-xs leading-relaxed text-slate-200 hover:bg-emerald-500/15 hover:text-emerald-300">
                                    <span x-text="p.text"></span>
                                    <span class="ms-1 text-[10px] opacity-60"
                                        x-text="p.color === 'red' ? '(أحمر)' : (p.color === 'green' ? '(أخضر)' : '')"></span>
                                </button>
                            </template>
                        </div>

                        <template x-for="(c, idx) in comments" :key="idx">
                            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                <textarea :name="`comments[${idx}][text]`" x-model="c.text" rows="2"
                                    class="mb-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"
                                    placeholder="نص التعليق..."></textarea>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex gap-1">
                                        <template x-for="opt in [{v:'none',l:'بدون'},{v:'red',l:'أحمر'},{v:'green',l:'أخضر'}]" :key="opt.v">
                                            <button type="button" @click="c.color = opt.v"
                                                class="rounded-md px-3 py-1 text-xs font-bold"
                                                :class="{
                                                    'bg-slate-600 text-white': c.color === opt.v && opt.v === 'none',
                                                    'bg-red-500 text-white': c.color === opt.v && opt.v === 'red',
                                                    'bg-lime-400 text-slate-900': c.color === opt.v && opt.v === 'green',
                                                    'bg-slate-800 text-slate-500': c.color !== opt.v
                                                }"
                                                x-text="opt.l"></button>
                                        </template>
                                        <input type="hidden" :name="`comments[${idx}][color]`" :value="c.color">
                                    </div>
                                    <button type="button" @click="comments.splice(idx,1)" class="text-xs text-rose-400">حذف</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    {{-- STEP 3 --}}
                    <div x-show="step === 3" class="space-y-4">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-3">
                            <input type="checkbox" name="has_table" value="1" x-model="hasTable"
                                class="h-4 w-4 rounded border-slate-600 text-emerald-500">
                            <span class="text-sm font-semibold text-slate-200">إضافة جدول لهذا القسم</span>
                        </label>

                        <div x-show="hasTable" class="space-y-4">

                            {{-- ── Excel Import Bar (works for BOTH table types) ── --}}
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3">
                                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span class="text-xs font-semibold text-emerald-300">استيراد من Excel</span>
                                <label class="cursor-pointer rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400 transition-colors">
                                    <span x-text="excelImporting ? 'جاري القراءة...' : 'اختر ملف Excel'"></span>
                                    <input type="file" accept=".xlsx,.xls,.csv" class="hidden" :disabled="excelImporting" @change="importFromExcel($event)">
                                </label>
                                <span x-show="excelFileName" class="truncate text-[11px] text-emerald-400 font-medium" x-text="'✓ ' + excelFileName"></span>
                                <div class="ms-auto hidden flex-col items-end sm:flex">
                                    <span class="text-[10px] text-slate-500">يدعم: جدول MCB (صورة 1) · جدول التأريض (صورة 2) · جدول مفصّل · جدول ملخص</span>
                                    <span class="text-[10px] text-slate-600">يقرأ الألوان تلقائياً من خلفيات خلايا Excel</span>
                                </div>
                            </div>

                            {{-- ── Table type + title ── --}}
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs text-slate-400">نوع الجدول</label>
                                    <select name="table_type" x-model="tableType"
                                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-white">
                                        <option value="grounding_main">فحص التأريض واللوحة الرئيسية</option>
                                        <option value="mcb_panel">لوحة توزيع (MCB / ELCB)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-slate-400">عنوان الجدول</label>
                                    <input name="table_title" x-model="tableTitle"
                                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-white"
                                        placeholder="جدول 1 فحص التأريض واللوحة الرئيسية">
                                </div>
                            </div>

                            {{-- ════════════ Grounding ════════════ --}}
                            <div x-show="tableType === 'grounding_main'" class="space-y-3 rounded-xl border border-slate-700 p-3">

                                {{-- Section: فحص التأريض --}}
                                <p class="text-center text-xs font-bold text-slate-300 border-b border-slate-700 pb-2">فحص التأريض</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-[11px] text-slate-500">هل كابل التأريض موجود؟</label>
                                        <div class="flex gap-2">
                                            <input name="grounding_cable_value" x-model="grounding_cable_value"
                                                @change="grounding_cable_color = $event.target.value ? (autoColorFn($event.target.value) || grounding_cable_color) : grounding_cable_color"
                                                class="flex-1 rounded-lg border px-2 py-2 text-sm text-white font-semibold transition-colors"
                                                :class="{
                                                    'border-emerald-500/60 bg-emerald-900/20 text-emerald-300': grounding_cable_color === 'green',
                                                    'border-red-500/60 bg-red-900/20 text-red-300':             grounding_cable_color === 'red',
                                                    'border-slate-700 bg-slate-950':                             grounding_cable_color === 'none'
                                                }">
                                            <select name="grounding_cable_color" x-model="grounding_cable_color"
                                                class="w-24 rounded-lg border text-sm text-white transition-colors"
                                                :class="{
                                                    'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': grounding_cable_color === 'green',
                                                    'border-red-500/60 bg-red-900/40 text-red-300':             grounding_cable_color === 'red',
                                                    'border-slate-700 bg-slate-900':                             grounding_cable_color === 'none'
                                                }">
                                                <option value="green">أخضر ✓</option>
                                                <option value="red">أحمر ✗</option>
                                                <option value="none">بدون —</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] text-slate-500">قراءة فحص التأريض</label>
                                        <div class="flex gap-2">
                                            <input name="grounding_reading_value" x-model="grounding_reading_value"
                                                @change="grounding_reading_color = $event.target.value ? (autoColorFn($event.target.value) || grounding_reading_color) : grounding_reading_color"
                                                placeholder="0.59 Ohm"
                                                class="flex-1 rounded-lg border px-2 py-2 text-sm text-white font-semibold transition-colors"
                                                :class="{
                                                    'border-emerald-500/60 bg-emerald-900/20 text-emerald-300': grounding_reading_color === 'green',
                                                    'border-red-500/60 bg-red-900/20 text-red-300':             grounding_reading_color === 'red',
                                                    'border-slate-700 bg-slate-950':                             grounding_reading_color === 'none'
                                                }">
                                            <select name="grounding_reading_color" x-model="grounding_reading_color"
                                                class="w-24 rounded-lg border text-sm text-white transition-colors"
                                                :class="{
                                                    'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': grounding_reading_color === 'green',
                                                    'border-red-500/60 bg-red-900/40 text-red-300':             grounding_reading_color === 'red',
                                                    'border-slate-700 bg-slate-900':                             grounding_reading_color === 'none'
                                                }">
                                                <option value="green">أخضر ✓</option>
                                                <option value="red">أحمر ✗</option>
                                                <option value="none">بدون —</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- Section: ضغط لوحة التوزيع الرئيسية --}}
                                <p class="text-center text-xs font-bold text-slate-300 border-b border-slate-700 pb-2 pt-2">ضغط لوحة التوزيع الرئيسية</p>
                                <div class="flex items-center justify-end">
                                    <button type="button" @click="addVoltage()" class="text-xs text-emerald-400 font-semibold">+ صف جهد</button>
                                </div>
                                <template x-for="(v, idx) in voltages" :key="idx">
                                    <div class="grid grid-cols-12 gap-1 mb-1">
                                        <input :name="`voltages[${idx}][label]`" x-model="v.label"
                                            placeholder="R-Y"
                                            class="col-span-3 rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-white text-center font-semibold">
                                        <input :name="`voltages[${idx}][value]`" x-model="v.value"
                                            placeholder="408.6 VOLT"
                                            class="col-span-6 rounded border px-2 py-1.5 text-xs font-semibold transition-colors"
                                            :class="{
                                                'border-emerald-500/60 bg-emerald-900/20 text-emerald-300': v.color === 'green',
                                                'border-red-500/60 bg-red-900/20 text-red-300':             v.color === 'red',
                                                'border-slate-700 bg-slate-950 text-white':                  v.color === 'none'
                                            }">
                                        <select :name="`voltages[${idx}][color]`" x-model="v.color"
                                            class="col-span-2 rounded border text-xs transition-colors"
                                            :class="{
                                                'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': v.color === 'green',
                                                'border-red-500/60 bg-red-900/40 text-red-300':             v.color === 'red',
                                                'border-slate-700 bg-slate-900 text-white':                  v.color === 'none'
                                            }">
                                            <option value="none">—</option>
                                            <option value="green">G ✓</option>
                                            <option value="red">R ✗</option>
                                        </select>
                                        <button type="button" @click="voltages.splice(idx,1)" class="col-span-1 text-rose-400 hover:text-rose-300">×</button>
                                    </div>
                                </template>
                            </div>

                            {{-- ════════════ MCB Panel ════════════ --}}
                            <div x-show="tableType === 'mcb_panel'" class="space-y-3">

                                <p class="text-xs text-slate-500">
                                    كل مجموعة = قاطع حماية (ELCB). ترتيب الأعمدة مثل التقرير:
                                    <span class="font-semibold text-slate-300">MCB → Cable → Status</span>
                                    (يسار ثم يمين). العمود # في الإكسل يُتجاهل عمداً.
                                </p>
                                <p class="text-[11px] text-slate-500" x-show="mcbGroups.length > 1">
                                    عدد القواطع المستوردة:
                                    <span class="font-bold text-emerald-400" x-text="mcbGroups.length"></span>
                                </p>

                                <template x-for="(g, gi) in mcbGroups" :key="gi">
                                    <div class="rounded-xl border border-slate-700 bg-slate-950/40 p-3">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <span class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-400"
                                                x-text="'قاطع ' + (gi + 1) + ' / ' + mcbGroups.length"></span>
                                            <span class="truncate text-[10px] text-slate-500" x-show="g.source_sheet"
                                                x-text="g.source_sheet"></span>
                                        </div>
                                        <div class="mb-3 space-y-2">
                                            <div>
                                                <label class="mb-0.5 block text-[10px] text-slate-500">ELCB / الطابق</label>
                                                <input :name="`mcb_groups[${gi}][elcb]`" x-model="g.elcb"
                                                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                                    dir="rtl">
                                            </div>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-0.5 block text-[10px] text-slate-500">Have Label?</label>
                                                    <input :name="`mcb_groups[${gi}][have_label_value]`" x-model="g.have_label_value"
                                                        @change="applyAutoColor(g, 'have_label_value')"
                                                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                                        :class="{
                                                            'border-emerald-500/60 text-emerald-300': g.have_label_color === 'green' && g.have_label_value,
                                                            'border-red-500/60 text-red-300':     g.have_label_color === 'red'   && g.have_label_value
                                                        }">
                                                </div>
                                                <div>
                                                    <label class="mb-0.5 block text-[10px] text-slate-500">لون Label</label>
                                                    <select :name="`mcb_groups[${gi}][have_label_color]`" x-model="g.have_label_color"
                                                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                                        :class="{
                                                            'bg-emerald-900/40 text-emerald-300': g.have_label_color === 'green',
                                                            'bg-red-900/40 text-red-300':         g.have_label_color === 'red'
                                                        }">
                                                        <option value="green">أخضر ✓</option>
                                                        <option value="red">أحمر ✗</option>
                                                        <option value="none">بدون —</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="overflow-x-auto" dir="ltr">
                                            <table class="min-w-[640px] w-full text-[10px]">
                                                <thead class="text-slate-500">
                                                    <tr>
                                                        <th class="px-1 py-1 text-center" colspan="3">Left</th>
                                                        <th class="px-1 py-1 text-center" colspan="3">Right</th>
                                                        <th></th>
                                                    </tr>
                                                    <tr>
                                                        <th>MCB Rating</th><th>Cable Size</th><th>Status</th>
                                                        <th>MCB Rating</th><th>Cable Size</th><th>Status</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="(row, ri) in g.rows" :key="ri">
                                                        <tr class="border-t border-slate-800">

                                                            {{-- Left MCB --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][left_mcb]`"
                                                                    x-model="row.left_mcb"
                                                                    @change="applyAutoColor(row,'left_mcb')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20': row.left_mcb_color === 'green' && row.left_mcb,
                                                                        'border-red-500/60 bg-red-900/20':         row.left_mcb_color === 'red'   && row.left_mcb,
                                                                        'border-slate-700 bg-slate-900':           !row.left_mcb || row.left_mcb_color === 'none'
                                                                    }"
                                                                    placeholder="10A">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][left_mcb_color]`"
                                                                    x-model="row.left_mcb_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.left_mcb_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.left_mcb_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.left_mcb_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            {{-- Left Cable --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][left_cable]`"
                                                                    x-model="row.left_cable"
                                                                    @change="applyAutoColor(row,'left_cable')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20': row.left_cable_color === 'green' && row.left_cable,
                                                                        'border-red-500/60 bg-red-900/20':         row.left_cable_color === 'red'   && row.left_cable,
                                                                        'border-slate-700 bg-slate-900':           !row.left_cable || row.left_cable_color === 'none'
                                                                    }"
                                                                    placeholder="1.5">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][left_cable_color]`"
                                                                    x-model="row.left_cable_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.left_cable_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.left_cable_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.left_cable_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            {{-- Left Status --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][left_status]`"
                                                                    x-model="row.left_status"
                                                                    @change="applyAutoColor(row,'left_status')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white font-semibold transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20 text-emerald-300': row.left_status_color === 'green' && row.left_status,
                                                                        'border-red-500/60 bg-red-900/20 text-red-300':             row.left_status_color === 'red'   && row.left_status,
                                                                        'border-slate-700 bg-slate-900':                             !row.left_status || row.left_status_color === 'none'
                                                                    }"
                                                                    placeholder="Valid">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][left_status_color]`"
                                                                    x-model="row.left_status_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.left_status_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.left_status_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.left_status_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            {{-- Right MCB --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][right_mcb]`"
                                                                    x-model="row.right_mcb"
                                                                    @change="applyAutoColor(row,'right_mcb')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20': row.right_mcb_color === 'green' && row.right_mcb,
                                                                        'border-red-500/60 bg-red-900/20':         row.right_mcb_color === 'red'   && row.right_mcb,
                                                                        'border-slate-700 bg-slate-900':           !row.right_mcb || row.right_mcb_color === 'none'
                                                                    }"
                                                                    placeholder="30A">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][right_mcb_color]`"
                                                                    x-model="row.right_mcb_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.right_mcb_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.right_mcb_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.right_mcb_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            {{-- Right Cable --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][right_cable]`"
                                                                    x-model="row.right_cable"
                                                                    @change="applyAutoColor(row,'right_cable')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20': row.right_cable_color === 'green' && row.right_cable,
                                                                        'border-red-500/60 bg-red-900/20':         row.right_cable_color === 'red'   && row.right_cable,
                                                                        'border-slate-700 bg-slate-900':           !row.right_cable || row.right_cable_color === 'none'
                                                                    }"
                                                                    placeholder="6">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][right_cable_color]`"
                                                                    x-model="row.right_cable_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.right_cable_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.right_cable_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.right_cable_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            {{-- Right Status --}}
                                                            <td class="p-0.5">
                                                                <input :name="`mcb_groups[${gi}][rows][${ri}][right_status]`"
                                                                    x-model="row.right_status"
                                                                    @change="applyAutoColor(row,'right_status')"
                                                                    class="mb-0.5 w-full rounded border px-1 py-1 text-white font-semibold transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/20 text-emerald-300': row.right_status_color === 'green' && row.right_status,
                                                                        'border-red-500/60 bg-red-900/20 text-red-300':             row.right_status_color === 'red'   && row.right_status,
                                                                        'border-slate-700 bg-slate-900':                             !row.right_status || row.right_status_color === 'none'
                                                                    }"
                                                                    placeholder="Valid">
                                                                <select :name="`mcb_groups[${gi}][rows][${ri}][right_status_color]`"
                                                                    x-model="row.right_status_color"
                                                                    class="w-full rounded border text-white transition-colors"
                                                                    :class="{
                                                                        'border-emerald-500/60 bg-emerald-900/40 text-emerald-300': row.right_status_color === 'green',
                                                                        'border-red-500/60 bg-red-900/40 text-red-300':             row.right_status_color === 'red',
                                                                        'border-slate-700 bg-slate-900':                             row.right_status_color === 'none'
                                                                    }">
                                                                    <option value="green">G ✓</option><option value="red">R ✗</option><option value="none">—</option>
                                                                </select>
                                                            </td>

                                                            <td class="p-0.5 align-middle">
                                                                <button type="button" @click="g.rows.splice(ri,1)" class="text-rose-400 hover:text-rose-300 text-base leading-none">×</button>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2 flex gap-3">
                                            <button type="button" @click="addMcbRow(gi)" class="text-xs font-bold text-emerald-400">+ صف</button>
                                            <button type="button" x-show="mcbGroups.length > 1" @click="mcbGroups.splice(gi,1)" class="text-xs text-rose-400">حذف المجموعة</button>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" @click="addMcbGroup()" class="text-xs font-bold text-emerald-400">+ مجموعة ELCB</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer actions --}}
                <div class="flex items-center justify-between gap-3 border-t border-slate-800 bg-slate-950/60 px-5 py-4">
                    <button type="button" @click="prevStep()" x-show="step > 1"
                        class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-bold text-slate-300 hover:bg-slate-800">السابق</button>
                    <div class="ms-auto flex gap-2">
                        <button type="button" @click="closeModal()"
                            class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-bold text-slate-400 hover:bg-slate-800">إلغاء</button>
                        <button type="button" @click="nextStep()" x-show="step < 3"
                            class="rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-white">التالي</button>
                        <button type="submit" x-show="step === 3"
                            class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400"
                            x-text="editingId ? 'حفظ التعديلات' : 'إضافة القسم'"></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Image annotate modal --}}
<div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/80" @click="close()"></div>
    <div class="relative z-10 flex max-h-[95vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
            <h3 class="font-bold text-white">تعديل الصورة — إضافة سهم</h3>
            <button type="button" class="text-slate-400 hover:text-white" @click="close()">إغلاق</button>
        </div>
        <div class="grid gap-4 overflow-y-auto p-4 lg:grid-cols-12">
            <div class="lg:col-span-8 flex justify-center">
                <div class="relative overflow-hidden rounded-xl border border-emerald-500/30 bg-black/40"
                    x-ref="viewport" @click="onViewportClick($event)">
                    <img x-ref="img" :src="imageUrl" alt="" class="block select-none" draggable="false"
                        @load="setupViewport()">
                    <svg x-ref="svg" class="pointer-events-auto absolute inset-0 h-full w-full" viewBox="0 0 1000 1000" preserveAspectRatio="none"></svg>
                    <template x-for="(a, i) in arrows" :key="i">
                        <div data-drag-handle
                            class="absolute z-10 h-6 w-6 -translate-x-1/2 -translate-y-1/2 cursor-grab rounded-full border border-white/60 bg-red-500/40"
                            :style="`left:${a.x * viewW}px; top:${a.y * viewH}px`"
                            @mousedown="startDrag($event, i)"></div>
                    </template>
                </div>
            </div>
            <aside class="space-y-3 lg:col-span-4">
                <p class="text-xs text-slate-400">أضف سهماً ثم اسحبه أو انقر على الصورة لنقل رأس السهم.</p>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">حجم السهم</label>
                    <input type="range" min="0.4" max="2.5" step="0.05" x-model.number="arrowSize" @input="syncSelectedStyle()" class="w-full accent-emerald-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">زاوية السهم</label>
                    <input type="range" min="-180" max="180" step="1" x-model.number="arrowAngle" @input="syncSelectedStyle()" class="w-full accent-sky-500">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="addArrow()" class="rounded-xl bg-emerald-500 py-2 text-xs font-bold text-slate-950">+ سهم</button>
                    <button type="button" @click="removeSelected()" class="rounded-xl border border-rose-500/40 py-2 text-xs font-bold text-rose-400">حذف المحدد</button>
                </div>
                <button type="button" @click="clearArrows()" class="w-full rounded-xl border border-slate-700 py-2 text-xs text-slate-300">مسح كل الأسهم</button>
                <button type="button" @click="save()" :disabled="saving"
                    class="w-full rounded-xl bg-white py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-300 disabled:opacity-50"
                    x-text="saving ? 'جاري الحفظ...' : 'حفظ الصورة في التقرير'"></button>
            </aside>
        </div>
    </div>
</div>
</div>
@endsection
