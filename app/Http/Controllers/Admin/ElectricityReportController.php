<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectricityReport;
use App\Services\ElectricityReportPdfGenerator;
use App\Services\ElectricityReportWordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ElectricityReportController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $reports = ElectricityReport::query()
            ->select([
                'id', 'title', 'client_name', 'reference_code',
                'inspection_date', 'villa_number', 'compound', 'area', 'created_at',
            ])
            ->withCount('sections')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('client_name', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('reference_code', 'like', $like)
                        ->orWhere('villa_number', 'like', $like)
                        ->orWhere('compound', 'like', $like)
                        ->orWhere('area', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.index', compact('reports', 'search'));
    }

    public function create(): View
    {
        return view('admin.reports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedMeta($request);

        $report = ElectricityReport::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'تم إنشاء التقرير. أضف الأقسام والجداول الآن.');
    }

    public function show(ElectricityReport $report): View
    {
        $report->load([
            'sections' => fn ($q) => $q
                ->orderBy('sort_order')
                ->orderBy('id')
                ->select([
                    'id', 'electricity_report_id', 'name', 'description',
                    'comments_json', 'images_json', 'has_table', 'table_type',
                    'table_title', 'table_data', 'sort_order',
                ]),
        ]);

        $presetDescriptions = cache()->remember('ready_presets.descriptions', 300, function () {
            return \App\Models\ReadyPreset::query()
                ->where('type', 'description')
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->pluck('body')
                ->values()
                ->all();
        });

        $presetComments = cache()->remember('ready_presets.comments', 300, function () {
            return \App\Models\ReadyPreset::query()
                ->where('type', 'comment')
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get(['body', 'color'])
                ->map(fn ($p) => ['text' => $p->body, 'color' => $p->color ?: 'none'])
                ->values()
                ->all();
        });

        return view('admin.reports.show', compact('report', 'presetDescriptions', 'presetComments'));
    }

    public function edit(ElectricityReport $report): View
    {
        return view('admin.reports.edit', compact('report'));
    }

    public function update(Request $request, ElectricityReport $report): RedirectResponse
    {
        $report->update($this->validatedMeta($request));

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'تم تحديث بيانات التقرير.');
    }

    public function destroy(ElectricityReport $report): RedirectResponse
    {
        $report->load('sections');
        foreach ($report->sections as $section) {
            $section->deleteStoredImages();
        }
        $report->delete();

        return redirect()
            ->route('admin.reports.index')
            ->with('status', 'تم حذف التقرير.');
    }

    public function reportPdf(ElectricityReport $report, Request $request): Response
    {
        $report->load(['sections' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $binary = app(ElectricityReportPdfGenerator::class)->renderBinary($report);
        $filename = 'electricity-report-'.$report->reportNumber().'.pdf';

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }

    public function reportWord(ElectricityReport $report): Response
    {
        $report->load(['sections' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $binary = app(ElectricityReportWordGenerator::class)->renderBinary($report);
        $filename = 'electricity-report-'.$report->reportNumber().'.docx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedMeta(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:120'],
            'inspection_date' => ['nullable', 'date'],
            'villa_number' => ['nullable', 'string', 'max:255'],
            'road' => ['nullable', 'string', 'max:255'],
            'compound' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:8000'],
        ]);
    }
}
