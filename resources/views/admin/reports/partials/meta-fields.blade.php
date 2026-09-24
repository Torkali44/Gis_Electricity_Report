@php
    $report = $report ?? null;
@endphp
<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-300">عنوان التقرير *</label>
        <input name="title" required value="{{ old('title', $report->title ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none"
            placeholder="مثال: تقرير الكهرباء فحص منزل سند 2060">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">اسم العميل</label>
        <input name="client_name" value="{{ old('client_name', $report->client_name ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">رقم التقرير</label>
        <input name="reference_code" value="{{ old('reference_code', $report->reference_code ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none"
            placeholder="E-2060">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">تاريخ الفحص</label>
        <input type="date" name="inspection_date" value="{{ old('inspection_date', optional($report->inspection_date ?? null)->format('Y-m-d')) }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">العنوان</label>
        <input name="address" value="{{ old('address', $report->address ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">رقم الفيلا</label>
        <input name="villa_number" value="{{ old('villa_number', $report->villa_number ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">الطريق</label>
        <input name="road" value="{{ old('road', $report->road ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">المجمع</label>
        <input name="compound" value="{{ old('compound', $report->compound ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-300">المنطقة</label>
        <input name="area" value="{{ old('area', $report->area ?? '') }}"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-300">ملاحظات داخلية</label>
        <textarea name="notes" rows="3"
            class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none">{{ old('notes', $report->notes ?? '') }}</textarea>
    </div>
</div>
