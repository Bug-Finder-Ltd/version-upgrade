<?php
use Illuminate\Support\Facades\Route;
use BugFinder\VersionUpgrade\Http\Controllers\UpdateVersionController;

Route::middleware(['auth:admin'])->group(function () {

    Route::get('version-upgradation', [UpdateVersionController::class, 'upgradationUI'])->name('admin.version.upgradation');
    Route::post('updates/check', [UpdateVersionController::class, 'check'])->name('admin.updates.check');
    Route::post('updates/install', [UpdateVersionController::class, 'install'])->name('admin.updates.install');
    Route::post('updates/status', [UpdateVersionController::class, 'status'])->name('admin.updates.status');
    Route::get('download/server/files', [UpdateVersionController::class, 'downloadServerFiles'])->name('admin.download.server.files');
    Route::get('download/db', [UpdateVersionController::class, 'downloadDatabase'])->name('admin.download.db');
});
