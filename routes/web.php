<?php
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
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
    Route::post('/organizations/{organization}/roles', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/organizations/{organization}/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/organizations/{organization}/roles/{role}', [RoleController::class, 'delete'])->name('roles.delete');
    Route::post('/organizations/{organization}/members', [RoleController::class, 'addMember'])->name('members.add');
    Route::post('/organizations/{organization}/members/{member}/roles', [RoleController::class, 'assign'])->name('assignments.update');
    Route::get('/organizations/{organization}/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/organizations/{organization}/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/organizations/{organization}/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::post('/organizations/{organization}/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/organizations/{organization}/employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])->name('documents.index');
    Route::post('/organizations/{organization}/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
    Route::get('/organizations/{organization}/employees/{employee}/documents/{document}/preview', [EmployeeDocumentController::class, 'preview'])->name('documents.preview');
    Route::get('/organizations/{organization}/employees/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('documents.download');
    Route::post('/organizations/{organization}/employees/{employee}/documents/{document}/archive', [EmployeeDocumentController::class, 'archive'])->name('documents.archive');
    Route::post('/organizations/{organization}/employees/{employee}/documents/{document}/restore', [EmployeeDocumentController::class, 'restore'])->name('documents.restore');
    Route::post('/organizations/{organization}/branding', [FoundationController::class, 'branding'])->name('branding.update');
});
