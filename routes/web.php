<?php

use App\Http\Controllers\Admin\ElectricityReportController;
use App\Http\Controllers\Admin\ReadyPresetController;
use App\Http\Controllers\Admin\ReportSectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:12,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.reports.index'));

    Route::get('presets', [ReadyPresetController::class, 'index'])->name('presets.index');
    Route::post('presets', [ReadyPresetController::class, 'store'])->name('presets.store');
    Route::patch('presets/{preset}', [ReadyPresetController::class, 'update'])->name('presets.update');
    Route::delete('presets/{preset}', [ReadyPresetController::class, 'destroy'])->name('presets.destroy');

    Route::get('reports/{report}/report.pdf', [ElectricityReportController::class, 'reportPdf'])
        ->middleware('throttle:20,1')
        ->name('reports.pdf');
    Route::get('reports/{report}/report.docx', [ElectricityReportController::class, 'reportWord'])
        ->middleware('throttle:20,1')
        ->name('reports.word');
    Route::resource('reports', ElectricityReportController::class);

    Route::post('reports/{report}/sections', [ReportSectionController::class, 'store'])->name('reports.sections.store');
    Route::patch('reports/{report}/sections/{section}', [ReportSectionController::class, 'update'])->name('reports.sections.update');
    Route::patch('reports/{report}/sections/{section}/reorder', [ReportSectionController::class, 'reorder'])->name('reports.sections.reorder');
    Route::post('reports/{report}/sections/{section}/image', [ReportSectionController::class, 'updateImage'])->name('reports.sections.image');
    Route::delete('reports/{report}/sections/{section}', [ReportSectionController::class, 'destroy'])->name('reports.sections.destroy');
});
