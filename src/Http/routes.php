<?php

use Illuminate\Support\Facades\Route;
use BugFinder\VersionUpgrade\Http\Controllers\UpdateVersionController;

$basicControl = basicControl();
Route::middleware(['web', 'auth:admin', 'demo'])
	->prefix($basicControl->admin_prefix ?? 'admin')
	->as('admin.')
	->group(function () {

		Route::get('version-upgradation', [UpdateVersionController::class, 'upgradationUI'])
			->name('version.upgradation');

		Route::post('updates/check', [UpdateVersionController::class, 'check'])
			->name('updates.check');

		Route::post('updates/install', [UpdateVersionController::class, 'install'])
			->name('updates.install');

		Route::post('updates/status', [UpdateVersionController::class, 'status'])
			->name('updates.status');

		Route::get('download/server/files', [UpdateVersionController::class, 'downloadServerFiles'])
			->name('download.server.files');

		Route::get('download/db', [UpdateVersionController::class, 'downloadDatabase'])
			->name('download.db');
	});
