<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectricityReport;
use App\Models\ReportSection;
use App\Support\HighlightColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportSectionController extends Controller
{
    public function store(Request $request, ElectricityReport $report): RedirectResponse
    {
        $data = $this->validatedSection($request);
        $maxOrder = (int) $report->sections()->max('sort_order');

        $images = $this->storeUploadedImages($request, $report->id);

        $report->sections()->create([
            ...$data,
            'images_json' => $images,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'تمت إضافة القسم.');
    }

    public function update(Request $request, ElectricityReport $report, ReportSection $section): RedirectResponse
    {
        $this->assertOwns($report, $section);

        $data = $this->validatedSection($request);

        $existing = is_array($section->images_json) ? $section->images_json : [];
        $remove = $request->input('remove_images', []);
        if (is_array($remove) && $remove !== []) {
            $keep = [];
            foreach ($existing as $path) {
                if (in_array($path, $remove, true)) {
                    Storage::disk('public')->delete($path);
                } else {
                    $keep[] = $path;
                }
            }
            $existing = $keep;
        }

        $newImages = $this->storeUploadedImages($request, $report->id);
        $merged = array_values(array_merge($existing, $newImages));
        // Keep a hard ceiling so storage / Word+PDF generation stay bounded
        $data['images_json'] = array_slice($merged, 0, 40);

        $section->update($data);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'تم تحديث القسم.');
    }

    public function reorder(Request $request, ElectricityReport $report, ReportSection $section): RedirectResponse
    {
        $this->assertOwns($report, $section);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $sections = $report->sections()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $sections->search(fn ($s) => $s->id === $section->id);
        if ($index === false) {
            return back();
        }

        $swapWith = $data['direction'] === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $sections->count()) {
            return back();
        }

        $a = $sections[$index];
        $b = $sections[$swapWith];
        $tmp = $a->sort_order;
        $a->update(['sort_order' => $b->sort_order]);
        $b->update(['sort_order' => $tmp]);

        return back()->with('status', 'تم إعادة الترتيب.');
    }

    public function destroy(ElectricityReport $report, ReportSection $section): RedirectResponse
    {
        $this->assertOwns($report, $section);
        $section->deleteStoredImages();
        $section->delete();

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'تم حذف القسم.');
    }

    private function assertOwns(ElectricityReport $report, ReportSection $section): void
    {
        abort_unless($section->electricity_report_id === $report->id, 404);
    }

    /** @return array<string, mixed> */
    private function validatedSection(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'has_table' => ['nullable', 'boolean'],
            'table_type' => ['nullable', Rule::in(['grounding_main', 'mcb_panel'])],
            'table_title' => ['nullable', 'string', 'max:255'],
            'comments' => ['nullable', 'array'],
            'comments.*.text' => ['nullable', 'string', 'max:4000'],
            'comments.*.color' => ['nullable', Rule::in(HighlightColor::all())],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['image', 'max:10240'],
            // grounding
            'grounding_cable_value' => ['nullable', 'string', 'max:120'],
            'grounding_cable_color' => ['nullable', Rule::in(HighlightColor::all())],
            'grounding_reading_value' => ['nullable', 'string', 'max:120'],
            'grounding_reading_color' => ['nullable', Rule::in(HighlightColor::all())],
            'voltages' => ['nullable', 'array'],
            'voltages.*.label' => ['nullable', 'string', 'max:40'],
            'voltages.*.value' => ['nullable', 'string', 'max:80'],
            'voltages.*.color' => ['nullable', Rule::in(HighlightColor::all())],
            // mcb
            'mcb_groups' => ['nullable', 'array', 'max:30'],
            'mcb_groups.*.elcb' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.have_label_value' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.have_label_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows' => ['nullable', 'array', 'max:80'],
            'mcb_groups.*.rows.*.left_mcb' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.left_mcb_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows.*.left_cable' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.left_cable_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows.*.left_status' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.left_status_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows.*.right_mcb' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.right_mcb_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows.*.right_cable' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.right_cable_color' => ['nullable', Rule::in(HighlightColor::all())],
            'mcb_groups.*.rows.*.right_status' => ['nullable', 'string', 'max:40'],
            'mcb_groups.*.rows.*.right_status_color' => ['nullable', Rule::in(HighlightColor::all())],
        ]);

        $hasTable = $request->boolean('has_table');
        $tableType = $hasTable ? ($validated['table_type'] ?? null) : null;
        $tableData = null;

        if ($hasTable && $tableType === 'grounding_main') {
            $voltages = [];
            foreach ($validated['voltages'] ?? [] as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' && $value === '') {
                    continue;
                }
                $voltages[] = [
                    'label' => $label,
                    'value' => $value,
                    'color' => HighlightColor::normalize($row['color'] ?? null),
                ];
            }
            $tableData = [
                'grounding_cable' => [
                    'value' => trim((string) ($validated['grounding_cable_value'] ?? '')),
                    'color' => HighlightColor::normalize($validated['grounding_cable_color'] ?? null),
                ],
                'grounding_reading' => [
                    'value' => trim((string) ($validated['grounding_reading_value'] ?? '')),
                    'color' => HighlightColor::normalize($validated['grounding_reading_color'] ?? null),
                ],
                'voltages' => $voltages,
            ];
        }

        if ($hasTable && $tableType === 'mcb_panel') {
            $groups = [];
            foreach ($validated['mcb_groups'] ?? [] as $group) {
                $rows = [];
                foreach ($group['rows'] ?? [] as $row) {
                    $leftEmpty = trim((string) ($row['left_mcb'] ?? '')) === ''
                        && trim((string) ($row['left_cable'] ?? '')) === ''
                        && trim((string) ($row['left_status'] ?? '')) === '';
                    $rightEmpty = trim((string) ($row['right_mcb'] ?? '')) === ''
                        && trim((string) ($row['right_cable'] ?? '')) === ''
                        && trim((string) ($row['right_status'] ?? '')) === '';
                    if ($leftEmpty && $rightEmpty) {
                        continue;
                    }
                    $rows[] = [
                        'left' => [
                            'mcb' => [
                                'value' => trim((string) ($row['left_mcb'] ?? '')),
                                'color' => HighlightColor::normalize($row['left_mcb_color'] ?? null),
                            ],
                            'cable' => [
                                'value' => trim((string) ($row['left_cable'] ?? '')),
                                'color' => HighlightColor::normalize($row['left_cable_color'] ?? null),
                            ],
                            'status' => [
                                'value' => trim((string) ($row['left_status'] ?? '')),
                                'color' => HighlightColor::normalize($row['left_status_color'] ?? null),
                            ],
                        ],
                        'right' => [
                            'mcb' => [
                                'value' => trim((string) ($row['right_mcb'] ?? '')),
                                'color' => HighlightColor::normalize($row['right_mcb_color'] ?? null),
                            ],
                            'cable' => [
                                'value' => trim((string) ($row['right_cable'] ?? '')),
                                'color' => HighlightColor::normalize($row['right_cable_color'] ?? null),
                            ],
                            'status' => [
                                'value' => trim((string) ($row['right_status'] ?? '')),
                                'color' => HighlightColor::normalize($row['right_status_color'] ?? null),
                            ],
                        ],
                    ];
                }
                $groups[] = [
                    'elcb' => trim((string) ($group['elcb'] ?? '')),
                    'have_label' => [
                        'value' => trim((string) ($group['have_label_value'] ?? '')),
                        'color' => HighlightColor::normalize($group['have_label_color'] ?? null),
                    ],
                    'rows' => $rows,
                ];
            }
            $tableData = ['groups' => $groups];
        }

        $comments = [];
        foreach ($validated['comments'] ?? [] as $c) {
            $text = trim((string) ($c['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $comments[] = [
                'text' => $text,
                'color' => HighlightColor::normalize($c['color'] ?? null),
            ];
        }

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'comments_json' => $comments,
            'has_table' => $hasTable,
            'table_type' => $tableType,
            'table_title' => $hasTable ? ($validated['table_title'] ?? null) : null,
            'table_data' => $tableData,
        ];
    }

    /** @return list<string> */
    private function storeUploadedImages(Request $request, int $reportId): array
    {
        $stored = [];
        if (! $request->hasFile('images')) {
            return $stored;
        }
        foreach ($request->file('images') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $stored[] = $file->store("electricity-reports/{$reportId}", 'public');
        }

        return $stored;
    }
}
