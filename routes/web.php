<?php

use App\Http\Controllers\Web\InviteRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/app/imoveis');
});

Route::view('/login', 'app')->name('login');
Route::post('/login', [\App\Http\Controllers\Web\SessionController::class, 'store'])->middleware('throttle:6,1');
Route::post('/logout', [\App\Http\Controllers\Web\SessionController::class, 'destroy'])->middleware('auth:web');
Route::get('/session/csrf', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'));
Route::view('/app/{path?}', 'app')->where('path', '.*')->middleware('auth:web');

require __DIR__.'/browser.php';

Route::get('/register', [InviteRegistrationController::class, 'create'])->name('register');
Route::post('/register', [InviteRegistrationController::class, 'store'])->name('register.store');
Route::get('/register/success', [InviteRegistrationController::class, 'success'])->name('register.success');
