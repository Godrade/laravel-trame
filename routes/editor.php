<?php

use Godrade\Trame\Http\Controllers\AssetController;
use Godrade\Trame\Http\Controllers\ContentController;
use Godrade\Trame\Http\Controllers\DefinitionsController;
use Godrade\Trame\Http\Controllers\EditorController;
use Godrade\Trame\Http\Controllers\MediaController;
use Godrade\Trame\Http\Controllers\PreviewController;
use Godrade\Trame\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

// Prefix, middleware ("can:trame.edit" included) and "trame." name are applied by the service provider.

Route::get('editor/{content}', [EditorController::class, 'show'])->name('editor');
Route::get('editor/{content}/frame', [EditorController::class, 'frame'])->name('editor.frame');
Route::get('assets/{file}', AssetController::class)->name('assets');

Route::prefix('api')->name('api.')->group(function () {
    Route::get('definitions', DefinitionsController::class)->name('definitions');

    Route::get('contents/{content}', [ContentController::class, 'show'])->name('contents.show');
    Route::put('contents/{content}', [ContentController::class, 'update'])->name('contents.update');
    Route::post('contents/{content}/publish', [ContentController::class, 'publish'])->name('contents.publish');
    Route::post('contents/{content}/unpublish', [ContentController::class, 'unpublish'])->name('contents.unpublish');

    Route::post('preview', PreviewController::class)->name('preview');

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');

    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
});
