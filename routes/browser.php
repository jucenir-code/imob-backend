<?php

use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\UserInviteController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\DealController;
use App\Http\Controllers\API\V1\DealMessageController;
use App\Http\Controllers\API\V1\GroupController;
use App\Http\Controllers\API\V1\GroupMemberController;
use App\Http\Controllers\API\V1\PropertyController;
use Illuminate\Support\Facades\Route;

// Loaded by web.php: native Laravel session, CSRF, and the existing domain controllers.
Route::prefix('web')->name('web.')->middleware('auth:web')->group(function () {
    Route::get('push/config', [\App\Http\Controllers\Web\WebPushController::class, 'config'])->name('push.config');
    Route::post('push/subscriptions', [\App\Http\Controllers\Web\WebPushController::class, 'store'])->middleware('throttle:30,1')->name('push.store');
    Route::delete('push/subscriptions', [\App\Http\Controllers\Web\WebPushController::class, 'destroy'])->name('push.destroy');
    Route::get('profile', [AuthController::class, 'me'])->name('profile');
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/members', [GroupMemberController::class, 'store'])->name('groups.members.store');
    Route::patch('groups/{group}/members/{member}', [GroupMemberController::class, 'update'])->name('groups.members.update');
    Route::delete('groups/{group}/members/{member}', [GroupMemberController::class, 'destroy'])->name('groups.members.destroy');
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::middleware('approved')->group(function () {
        Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
        Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
        Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
    });
    Route::apiResource('deals', DealController::class)->only(['index', 'show', 'store', 'update']);
    Route::get('deals/{deal}/messages', [DealMessageController::class, 'index'])->name('deals.messages.index');
    Route::post('deals/{deal}/messages', [DealMessageController::class, 'store'])->name('deals.messages.store');
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('users/pending', [UserController::class, 'pending'])->name('admin.users.pending');
        Route::post('users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
        Route::post('users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('invites', [UserInviteController::class, 'store'])->name('admin.invites.store');
    });
});
