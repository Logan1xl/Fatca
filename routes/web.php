<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Reports
    Route::resource('reports', ReportController::class);
    Route::get('reports/{report}/download-xml', [ReportController::class, 'downloadXml'])->name('reports.download_xml');
    Route::get('reports/{report}/download-corrected', [ReportController::class, 'downloadCorrectedXml'])->name('reports.download_corrected');
    Route::get('reports/{report}/download-pdf', [ReportController::class, 'downloadPdf'])->name('reports.download_pdf');
    Route::post('reports/{report}/encrypt', [ReportController::class, 'encrypt'])->name('reports.encrypt');

    // Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/generate-keys', [SettingsController::class, 'generateKeys'])->name('settings.generate-keys');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
