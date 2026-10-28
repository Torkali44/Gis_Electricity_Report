<?php

namespace App\Models;

use App\Support\HighlightColor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportSection extends Model
{
    protected $fillable = [
        'electricity_report_id',
        'name',
        'description',
        'comments_json',
        'images_json',
        'has_table',
        'table_type',
        'table_title',
        'table_data',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'comments_json' => 'array',
            'images_json' => 'array',
            'table_data' => 'array',
            'has_table' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ElectricityReport::class, 'electricity_report_id');
    }

    /** @return list<array{text: string, color: string}> */
    public function commentsList(): array
    {
        $raw = $this->comments_json;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $text = trim($item);
                if ($text !== '') {
                    $out[] = ['text' => $text, 'color' => HighlightColor::NONE];
                }

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'text' => $text,
                'color' => HighlightColor::normalize($item['color'] ?? HighlightColor::NONE),
            ];
        }

        return $out;
    }

    /** @return list<string> absolute filesystem paths that exist */
    public function imageAbsolutePaths(): array
    {
        $paths = is_array($this->images_json) ? $this->images_json : [];
        $absolute = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '') {
                continue;
            }
            $full = Storage::disk('public')->path($path);
            if (is_readable($full)) {
                $absolute[] = $full;
            }
        }

        return $absolute;
    }

    /** @return list<string> public URLs */
    /** @return list<array{path: string, url: string}> */
    public function imageEntries(): array
    {
        $paths = is_array($this->images_json) ? $this->images_json : [];
        $entries = [];
        foreach ($paths as $path) {
            $path = ltrim((string) $path, '/');
            if ($path === '' || ! Storage::disk('public')->exists($path)) {
                continue;
            }
            $entries[] = [
                'path' => $path,
                'url' => asset('storage/'.$path),
            ];
        }

        return $entries;
    }

    /** @return list<string> */
    public function imageUrls(): array
    {
        return array_column($this->imageEntries(), 'url');
    }

    public function deleteStoredImages(): void
    {
        $paths = is_array($this->images_json) ? $this->images_json : [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** Payload for the admin modal editor. */
    public function toEditorPayload(): array
    {
        $tableData = is_array($this->table_data) ? $this->table_data : [];
        $images = $this->imageEntries();

        $mcbGroups = [];
        foreach ($tableData['groups'] ?? [] as $g) {
            $rows = [];
            foreach ($g['rows'] ?? [] as $r) {
                $rows[] = [
                    'left_mcb' => $r['left']['mcb']['value'] ?? '',
                    'left_mcb_color' => $r['left']['mcb']['color'] ?? 'green',
                    'left_cable' => $r['left']['cable']['value'] ?? '',
                    'left_cable_color' => $r['left']['cable']['color'] ?? 'green',
                    'left_status' => $r['left']['status']['value'] ?? '',
                    'left_status_color' => $r['left']['status']['color'] ?? 'green',
                    'right_mcb' => $r['right']['mcb']['value'] ?? '',
                    'right_mcb_color' => $r['right']['mcb']['color'] ?? 'green',
                    'right_cable' => $r['right']['cable']['value'] ?? '',
                    'right_cable_color' => $r['right']['cable']['color'] ?? 'green',
                    'right_status' => $r['right']['status']['value'] ?? '',
                    'right_status_color' => $r['right']['status']['color'] ?? 'green',
                ];
            }
            $mcbGroups[] = [
                'elcb' => $g['elcb'] ?? '',
                'have_label_value' => $g['have_label']['value'] ?? '',
                'have_label_color' => $g['have_label']['color'] ?? 'green',
                'rows' => $rows ?: [[
                    'left_mcb' => '', 'left_mcb_color' => 'green', 'left_cable' => '', 'left_cable_color' => 'green',
                    'left_status' => 'Valid', 'left_status_color' => 'green',
                    'right_mcb' => '', 'right_mcb_color' => 'green', 'right_cable' => '', 'right_cable_color' => 'green',
                    'right_status' => 'Valid', 'right_status_color' => 'green',
                ]],
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'comments' => $this->commentsList() ?: [['text' => '', 'color' => 'none']],
            'images' => $images,
            'has_table' => (bool) $this->has_table,
            'table_type' => $this->table_type ?: 'mcb_panel',
            'table_title' => $this->table_title ?? '',
            'grounding_cable_value' => $tableData['grounding_cable']['value'] ?? 'نعم',
            'grounding_cable_color' => $tableData['grounding_cable']['color'] ?? 'green',
            'grounding_reading_value' => $tableData['grounding_reading']['value'] ?? '',
            'grounding_reading_color' => $tableData['grounding_reading']['color'] ?? 'green',
            'voltages' => $tableData['voltages'] ?? [
                ['label' => 'R-Y', 'value' => '', 'color' => 'none'],
                ['label' => 'R-B', 'value' => '', 'color' => 'none'],
                ['label' => 'Y-B', 'value' => '', 'color' => 'none'],
                ['label' => 'R-N', 'value' => '', 'color' => 'none'],
                ['label' => 'Y-N', 'value' => '', 'color' => 'none'],
                ['label' => 'B-N', 'value' => '', 'color' => 'none'],
            ],
            'mcb_groups' => $mcbGroups ?: [[
                'elcb' => '300mA',
                'have_label_value' => 'Yes',
                'have_label_color' => 'green',
                'rows' => [[
                    'left_mcb' => '', 'left_mcb_color' => 'green', 'left_cable' => '', 'left_cable_color' => 'green',
                    'left_status' => 'Valid', 'left_status_color' => 'green',
                    'right_mcb' => '', 'right_mcb_color' => 'green', 'right_cable' => '', 'right_cable_color' => 'green',
                    'right_status' => 'Valid', 'right_status_color' => 'green',
                ]],
            ]],
            'update_url' => route('admin.reports.sections.update', [$this->electricity_report_id, $this->id]),
        ];
    }
}