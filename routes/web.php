<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdmissionTrackController;
use App\Http\Controllers\Admin\AdmissionWaveController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentRequirementController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\FeeAmountController;
use App\Http\Controllers\Admin\FeeComponentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\MajorController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolUnitController;
use App\Http\Controllers\Admin\UnitFeeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Welcome Page
Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::any('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Admin Panel Group
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard Route
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:view-dashboard');

    // Profile Routes
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Activity Log Route
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('permission:activity-log-list');

    // RBAC CRUD Resource Routes
    Route::resource('users', UserController::class)->middleware('permission:user-list');
    Route::resource('roles', RoleController::class)->middleware('permission:role-list');
    Route::resource('permissions', PermissionController::class)->middleware('permission:permission-list');

    // SPMB Master Data Routes
    Route::resource('academic-years', AcademicYearController::class)->middleware('permission:academic-year-list');
    Route::resource('programs', ProgramController::class)->middleware('permission:program-list');
    Route::resource('school-units', SchoolUnitController::class)->middleware('permission:school-unit-list');
    Route::resource('admission-tracks', AdmissionTrackController::class)->middleware('permission:admission-track-list');
    Route::resource('majors', MajorController::class)->middleware('permission:major-list');
    // The resource parameter is pinned because Laravel's inflector would
    // otherwise singularize "waves" to "wafe" and break model binding.
    Route::resource('admission-waves', AdmissionWaveController::class)->parameters(['admission-waves' => 'admissionWave'])->middleware('permission:admission-wave-list');
    Route::resource('document-types', DocumentTypeController::class)->middleware('permission:document-type-list');
    Route::resource('form-fields', FormFieldController::class)->middleware('permission:form-field-list');

    // Registration form preview per unit
    Route::get('school-units/{school_unit}/form-preview', [FormFieldController::class, 'preview'])->name('school-units.form-preview');

    Route::resource('fee-components', FeeComponentController::class)->middleware('permission:fee-component-list');
    Route::resource('fee-amounts', FeeAmountController::class)->middleware('permission:fee-amount-list');

    // Fee mappings per unit
    Route::get('school-units/{school_unit}/fees', [UnitFeeController::class, 'edit'])->name('school-units.fees.edit');
    Route::put('school-units/{school_unit}/fees', [UnitFeeController::class, 'update'])->name('school-units.fees.update');

    // Per-unit document requirements (matrix), managed from the unit list
    Route::get('school-units/{school_unit}/document-requirements', [DocumentRequirementController::class, 'edit'])->name('school-units.document-requirements.edit');
    Route::put('school-units/{school_unit}/document-requirements', [DocumentRequirementController::class, 'update'])->name('school-units.document-requirements.update');
});
