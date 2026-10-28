<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReadyPreset;
use App\Support\HighlightColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReadyPresetController extends Controller
{
    public function index(): View
    {
        $descriptions = ReadyPreset::query()
            ->where('type', 'description')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $comments = ReadyPreset::query()
            ->where('type', 'comment')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('admin.presets.index', compact('descriptions', 'comments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['description', 'comment'])],
            'body' => ['required', 'string', 'max:4000'],
            'color' => ['nullable', Rule::in(HighlightColor::all())],
        ]);

        ReadyPreset::query()->create([
            'user_id' => $request->user()?->id,
            'type' => $data['type'],
            'body' => trim($data['body']),
            'color' => $data['type'] === 'comment'
                ? HighlightColor::normalize($data['color'] ?? 'none')
                : 'none',
            'sort_order' => 0,
        ]);

        $this->forgetPresetCache();

        return redirect()
            ->route('admin.presets.index', ['tab' => $data['type'] === 'comment' ? 'comments' : 'descriptions'])
            ->with('status', 'تمت الإضافة.');
    }

    public function update(Request $request, ReadyPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'color' => ['nullable', Rule::in(HighlightColor::all())],
        ]);

        $preset->update([
            'body' => trim($data['body']),
            'color' => $preset->isComment()
                ? HighlightColor::normalize($data['color'] ?? $preset->color)
                : 'none',
        ]);

        $this->forgetPresetCache();

        return redirect()
            ->route('admin.presets.index', ['tab' => $preset->isComment() ? 'comments' : 'descriptions'])
            ->with('status', 'تم التحديث.');
    }

    public function destroy(ReadyPreset $preset): RedirectResponse
    {
        $tab = $preset->isComment() ? 'comments' : 'descriptions';
        $preset->delete();
        $this->forgetPresetCache();

        return redirect()
            ->route('admin.presets.index', ['tab' => $tab])
            ->with('status', 'تم الحذف.');
    }

    private function forgetPresetCache(): void
    {
        cache()->forget('ready_presets.descriptions');
        cache()->forget('ready_presets.comments');
    }
}
