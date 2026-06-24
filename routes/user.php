<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mrsuner\ApiKeys\Http\Controllers\ApiKeyController;

/*
| Mounted by ApiKeyServiceProvider under the configured prefix
| (default: v1/api-keys) and the auth:sanctum guard. These endpoints let a
| user manage their OWN keys. The plaintext key is returned only by store and
| rotate, and only once.
*/

Route::get('/', [ApiKeyController::class, 'index'])->name('index');
Route::post('/', [ApiKeyController::class, 'store'])->name('store');

Route::get('{key}', [ApiKeyController::class, 'show'])->name('show');
Route::patch('{key}', [ApiKeyController::class, 'update'])->name('update');
Route::delete('{key}', [ApiKeyController::class, 'destroy'])->name('destroy');

Route::post('{key}/rotate', [ApiKeyController::class, 'rotate'])->name('rotate');
Route::get('{key}/usage', [ApiKeyController::class, 'usage'])->name('usage');
