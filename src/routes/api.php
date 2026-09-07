<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Global\Font\Http\Controllers\Admin\FontAdminController;
use Plugins\G7\Global\Font\Http\Controllers\FontCssController;
use Plugins\G7\Global\Font\Http\Controllers\FontFileController;

/*
 * g7-global-font 플러그인 API 라우트
 *
 * URL prefix: /api/plugins/g7-global-font (PluginRouteServiceProvider 자동 적용)
 */

// 전역 폰트 CSS (공개 — 전 페이지가 <link> 로 로드)
Route::get('font.css', [FontCssController::class, 'show'])
    ->name('font-css');

// 폰트 파일 스트리밍 (공개 — @font-face src 가 직접 접근)
Route::get('font-file/{id}', [FontFileController::class, 'serve'])
    ->whereNumber('id')
    ->name('font-file');

// 폰트 파일 관리 (관리자 인증 + 권한)
Route::prefix('admin')->name('admin.')->middleware('auth:sanctum')->group(function () {
    Route::get('fonts', [FontAdminController::class, 'index'])
        ->middleware('permission:admin,g7-global-font.fonts.manage')
        ->name('fonts.index');

    Route::post('fonts', [FontAdminController::class, 'store'])
        ->middleware('permission:admin,g7-global-font.fonts.manage')
        ->name('fonts.store');

    Route::delete('fonts/{id}', [FontAdminController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:admin,g7-global-font.fonts.manage')
        ->name('fonts.destroy');
});
