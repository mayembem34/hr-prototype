<?php
use App\Http\Controllers\FoundationController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [FoundationController::class, 'loginForm'])->name('login');
Route::post('/login', [FoundationController::class, 'login'])->middleware('throttle:6,1');
Route::post('/logout', [FoundationController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/', [FoundationController::class, 'index'])->name('home');
    Route::post('/organizations', [FoundationController::class, 'createOrganization'])->name('organizations.create');
    Route::get('/organizations/{organization}', [FoundationController::class, 'show'])->name('organizations.show');
    Route::post('/organizations/{organization}/admins', [FoundationController::class, 'addAdmin'])->name('organizations.admins');
    Route::post('/organizations/{organization}/units', [FoundationController::class, 'createUnit'])->name('units.create');
    Route::post('/organizations/{organization}/units/{unit}', [FoundationController::class, 'updateUnit'])->name('units.update');
    Route::post('/organizations/{organization}/branding', [FoundationController::class, 'branding'])->name('branding.update');
});
