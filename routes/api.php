<?php

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\GroupController;
use App\Http\Controllers\API\V1\DealController;
use App\Http\Controllers\API\V1\DealMessageController;
use App\Http\Controllers\API\V1\GroupMemberController;
use App\Http\Controllers\API\V1\PropertyController;
use App\Http\Controllers\API\V1\HealthController;
use App\Http\Controllers\API\V1\PushTokenController;
use App\Http\Controllers\API\Admin\UserController as AdminUserController;
use App\Http\Controllers\API\Admin\UserInviteController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('app.api_version'))
    ->name('api.v1.')
    ->group(function () {
        Route::get('health', HealthController::class)->name('health');

        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('notifications/push-token', [PushTokenController::class, 'store'])->name('notifications.push-token.store');

            Route::apiResource('groups', GroupController::class);
            Route::post('groups/{group}/members', [GroupMemberController::class, 'store'])->name('groups.members.store');
            Route::patch('groups/{group}/members/{member}', [GroupMemberController::class, 'update'])->name('groups.members.update');
            Route::delete('groups/{group}/members/{member}', [GroupMemberController::class, 'destroy'])->name('groups.members.destroy');

            // Properties - criar/editar/deletar requer aprovação
            Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
            Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
            Route::middleware('approved')->group(function () {
                Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
                Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
                Route::patch('properties/{property}', [PropertyController::class, 'update']);
                Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
            });
            Route::apiResource('deals', DealController::class)->only(['index', 'store', 'show', 'update']);
            Route::get('deals/{deal}/messages', [DealMessageController::class, 'index'])->name('deals.messages.index');
            Route::post('deals/{deal}/messages', [DealMessageController::class, 'store'])->name('deals.messages.store');

            // Admin routes
            Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
                Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
                Route::get('users/pending', [AdminUserController::class, 'pending'])->name('users.pending');
                Route::post('users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
                Route::post('users/{user}/reject', [AdminUserController::class, 'reject'])->name('users.reject');
                Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
                Route::post('invites', [UserInviteController::class, 'store'])->name('invites.store');
            });
        });
    });
