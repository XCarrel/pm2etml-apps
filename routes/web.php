<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DmzAssetController;
use App\Http\Controllers\JobDefinitionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect("/","dashboard");

//Authenticated
Route::middleware('auth')->group(function () {

    Route::get('/dashboard',DashboardController::class)->name('dashboard');

    Route::get('/jobs',[JobDefinitionController::class,'index'])->name('jobs');

    //Files (images) handling (avoid any injected script in image as returning the file as file !
    Route::get('/dmz-assets/{file}', DmzAssetController::class);

    //AUTH RELATED
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

//LOGIN
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

});


