<?php

declare(strict_types=1);

use DbCms\Core\Http\Middleware\CheckPermission;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupCreateGetController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupDeleteController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupEditGetController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupPostController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupPreviewGetController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupPutController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupsGetController;
use DbCms\Modules\Popups\Http\Controllers\Admin\PopupTargetsGetController;
use Illuminate\Support\Facades\Route;

Route::prefix('popups')->name('popups.')->middleware(CheckPermission::class.':manage:popups')->group(function (): void {
    Route::get('/', PopupsGetController::class)->name('index');
    Route::get('/targets', PopupTargetsGetController::class)->name('targets');
    Route::get('/create', PopupCreateGetController::class)->name('create');
    Route::post('/', PopupPostController::class)->name('store');
    Route::get('/{popup}/edit', PopupEditGetController::class)->name('edit');
    Route::put('/{popup}', PopupPutController::class)->name('update');
    Route::delete('/{popup}', PopupDeleteController::class)->name('destroy');
    Route::get('/{popup}/preview', PopupPreviewGetController::class)->name('preview');
});
