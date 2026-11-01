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
use App\Http\Controllers\AdmissionInfoController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\FormPaymentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Guardian\ApplicationController as GuardianApplicationController;
use App\Http\Controllers\Guardian\DashboardController as GuardianDashboardController;
use App\Http\Controllers\Guardian\InvoiceController as GuardianInvoiceController;
use App\Http\Controllers\GuardianAuthController;
use App\Http\Controllers\PaymentNotificationController;
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

// Public admission information
Route::get('/pendaftaran', [AdmissionInfoController::class, 'index'])->name('admissions.info');

// Guardian (Wali) registration
Route::middleware('guest')->group(function () {
    Route::get('/daftar', [GuardianAuthController::class, 'showRegister'])->name('guardian.register');
    Route::post('/daftar', [GuardianAuthController::class, 'register'])->name('guardian.register.store');
});

// Payment channel notifications (signature-verified, CSRF-exempt)
Route::post('/payments/midtrans/notification', [PaymentNotificationController::class, 'midtrans'])->name('payments.midtrans.notification');

// Guardian area: strict step order Register -> Bayar Formulir -> Isi
// Formulir -> Input Berkas; controllers guard each step server-side.
Route::middleware(['auth', 'role:Wali'])->prefix('wali')->name('guardian.')->group(function () {
    Route::get('/', [GuardianDashboardController::class, 'index'])->name('dashboard');
    Route::get('pendaftaran/baru', [GuardianApplicationController::class, 'create'])->name('applications.create');
    Route::post('pendaftaran', [GuardianApplicationController::class, 'store'])->name('applications.store');
    Route::get('pendaftaran/{application}', [GuardianApplicationController::class, 'show'])->name('applications.show');
    Route::get('pendaftaran/{application}/bayar', [GuardianApplicationController::class, 'payment'])->name('applications.payment');
    Route::post('pendaftaran/{application}/bayar', [GuardianApplicationController::class, 'pay'])->name('applications.pay');
    Route::post('pendaftaran/{application}/pembayaran/{payment}/nyatakan-lunas', [GuardianApplicationController::class, 'declarePaid'])->name('applications.declare-paid');
    Route::post('pendaftaran/{application}/pengajuan-biaya', [GuardianApplicationController::class, 'requestRelief'])->name('applications.request-relief');
    Route::get('pendaftaran/{application}/formulir', [GuardianApplicationController::class, 'form'])->name('applications.form');
    Route::post('pendaftaran/{application}/formulir', [GuardianApplicationController::class, 'saveForm'])->name('applications.form.save');
    Route::get('pendaftaran/{application}/berkas', [GuardianApplicationController::class, 'documents'])->name('applications.documents');
    Route::post('pendaftaran/{application}/berkas/{documentType}', [GuardianApplicationController::class, 'uploadDocument'])->name('applications.documents.upload');
    Route::delete('pendaftaran/{application}/berkas/{documentType}', [GuardianApplicationController::class, 'deleteDocument'])->name('applications.documents.delete');
    Route::post('pendaftaran/{application}/kirim', [GuardianApplicationController::class, 'submit'])->name('applications.submit');
    Route::get('pendaftaran/{application}/bukti', [GuardianApplicationController::class, 'proof'])->name('applications.proof');
    // Re-registration & invoice (Fase 4)
    Route::get('pendaftaran/{application}/daftar-ulang', [GuardianInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('pendaftaran/{application}/daftar-ulang', [GuardianInvoiceController::class, 'store'])->name('invoices.store');
    Route::post('pendaftaran/{application}/daftar-ulang/bayar', [GuardianInvoiceController::class, 'pay'])->name('invoices.pay');
    Route::post('pendaftaran/{application}/daftar-ulang/pembayaran/{payment}/nyatakan-lunas', [GuardianInvoiceController::class, 'declarePaid'])->name('invoices.declare-paid');
    Route::post('pendaftaran/{application}/daftar-ulang/pengajuan-diskon', [GuardianInvoiceController::class, 'requestDiscount'])->name('invoices.request-discount');
});

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

    // Form fee decisions & manual payment confirmations (Fase 2)
    Route::get('form-payments', [FormPaymentController::class, 'index'])->name('form-payments.index')->middleware('permission:form-payment-list');
    Route::post('form-payments/{application}/decide', [FormPaymentController::class, 'decide'])->name('form-payments.decide')->middleware('permission:form-payment-decide');
    Route::post('form-payments/payments/{payment}/confirm', [FormPaymentController::class, 'confirm'])->name('form-payments.confirm')->middleware('permission:form-payment-decide');

    // Application verification (Fase 3)
    Route::get('applications', [AdminApplicationController::class, 'index'])->name('applications.index')->middleware('permission:application-list');
    Route::get('applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.show')->middleware('permission:application-list');
    Route::post('applications/{application}/documents/{document}/verify', [AdminApplicationController::class, 'verifyDocument'])->name('applications.documents.verify')->middleware('permission:application-verify');
    Route::post('applications/{application}/decide', [AdminApplicationController::class, 'decide'])->name('applications.decide')->middleware('permission:application-verify');
    Route::post('applications/announce', [AdminApplicationController::class, 'announce'])->name('applications.announce')->middleware('permission:application-verify');

    // Re-registration invoices (Fase 4)
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('permission:invoice-list');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('permission:invoice-list');
    Route::post('invoices/{invoice}/decide-discount', [InvoiceController::class, 'decideDiscount'])->name('invoices.decide-discount')->middleware('permission:invoice-manage');
    Route::post('invoices/payments/{payment}/confirm', [InvoiceController::class, 'confirm'])->name('invoices.confirm')->middleware('permission:invoice-manage');

    // Settings (payment channels)
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit')->middleware('permission:manage-settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage-settings');
});
