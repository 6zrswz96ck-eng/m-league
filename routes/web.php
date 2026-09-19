<?php

use App\Http\Controllers\GroupController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RankingController;
use App\Http\Middleware\AdminAccess;
use Illuminate\Support\Facades\Route;
Route::get('/', [RankingController::class, 'index'])->name('ranking');
Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
Route::get('/players/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::post('/update', [RankingController::class, 'update'])->middleware('throttle:3,1')->name('update');
Route::get('/login', [AdminLoginController::class, 'show'])->name('login');
Route::post('/login', [AdminLoginController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');
Route::middleware(AdminAccess::class)->group(function () {
Route::get('/admin/groups', [GroupController::class, 'index'])->name('groups.index');
Route::post('/admin/groups', [GroupController::class, 'store'])->name('groups.store');
Route::put('/admin/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
Route::delete('/admin/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
Route::get('/admin/players', [PlayerController::class, 'manage'])->name('players.manage');
Route::post('/admin/players', [PlayerController::class, 'store'])->name('players.store');
Route::put('/admin/players/{player}', [PlayerController::class, 'update'])->name('players.update');
});
