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
    return view('welcome');
});

Route::get('/register', [InviteRegistrationController::class, 'create'])->name('register');
Route::post('/register', [InviteRegistrationController::class, 'store'])->name('register.store');
Route::get('/register/success', [InviteRegistrationController::class, 'success'])->name('register.success');
