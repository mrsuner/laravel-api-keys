<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mrsuner\ApiKeys\Http\Controllers\AdminApiKeyController;

/*
| Mounted by ApiKeyServiceProvider under the configured prefix
| (default: internal/admin/v1) and the boilerplate admin middleware stack.
| Admin endpoints never return the plaintext key or key_hash.
*/

Route::get('api-keys', [AdminApiKeyController::class, 'index'])->name('index');
Route::get('api-keys/{key}', [AdminApiKeyController::class, 'show'])->name('show');
Route::delete('api-keys/{key}', [AdminApiKeyController::class, 'destroy'])->name('destroy');

Route::get('users/{user}/api-keys', [AdminApiKeyController::class, 'forUser'])->name('users.index');
