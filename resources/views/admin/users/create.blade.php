@extends('layouts.admin')

@section('title', 'Tambah Pengguna')
@section('page-title', 'Tambah Pengguna')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-8 mx-auto">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Informasi Profil Pengguna</h5>
                    <p class="text-muted small mb-0">Isi data profil dasar untuk akun baru</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row g-3">
                        <!-- Name Field -->
                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold text-secondary">Nama Lengkap</label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   placeholder="John Doe" 
                                   required 
                                   autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email Field -->
                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold text-secondary">Alamat Email</label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   placeholder="johndoe@example.com" 
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Kredensial Keamanan</h5>
                    <p class="text-muted small mb-0">Atur kata sandi keamanan pengguna</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row g-3">
                        <!-- Password Field -->
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label fw-semibold text-secondary">Kata Sandi</label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Minimum 8 characters" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password Confirmation -->
                        <div class="col-12 col-md-6">
                            <label for="password_confirmation" class="form-label fw-semibold text-secondary">Konfirmasi Kata Sandi</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   placeholder="Repeat password" 
                                   required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Tetapkan Peran Sistem</h5>
                    <p class="text-muted small mb-0">Lampirkan peran untuk menentukan hak akses pengguna</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(count($roles) > 0)
                        <div class="d-flex flex-column gap-3">
                            @foreach($roles as $role)
                                <div class="card border p-3 rounded-3 bg-light bg-opacity-20">
                                    <div class="form-check d-flex align-items-center mb-0">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="roles[]" 
                                               value="{{ $role->name }}" 
                                               id="role-{{ $role->id }}"
                                               {{ $role->name === \App\Support\SystemAccess::USER_ROLE ? 'checked' : '' }}>
                                        <div class="ms-3">
                                            <label class="form-check-label fw-bold text-body mb-0" for="role-{{ $role->id }}">
                                                {{ $role->name }}
                                            </label>
                                            <span class="text-muted d-block small mt-0.5">
                                                @if($role->name === \App\Support\SystemAccess::SUPER_ADMIN_ROLE)
                                                    Akses admin sistem penuh tanpa batas (melewati semua pemeriksaan).
                                                @elseif($role->name === \App\Support\SystemAccess::ADMIN_ROLE)
                                                    Akses manajemen untuk melihat dasbor, mengubah pengguna, dan mengelola peran.
                                                @elseif($role->name === \App\Support\SystemAccess::USER_ROLE)
                                                    Akses pengguna standar, terbatas hanya untuk melihat detail dasbor.
                                                @else
                                                    Peran kustom dengan hak akses tertentu.
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-shield-slash fs-3 text-muted mb-2"></i>
                            <p class="text-muted small mb-0">Belum ada peran yang dibuat. Buat peran terlebih dahulu.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Penugasan Unit</h5>
                    <p class="text-muted small mb-0">Unit yang dapat dikelola pengguna ini sebagai panitia. Super Admin dan Admin yayasan mengakses semua unit tanpa penugasan.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(count($schoolUnits) > 0)
                        <div class="d-flex flex-column gap-3">
                            @foreach($schoolUnits as $unit)
                                <div class="card border p-3 rounded-3 bg-light bg-opacity-20">
                                    <div class="form-check d-flex align-items-center mb-0">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="school_units[]"
                                               value="{{ $unit->id }}"
                                               id="unit-{{ $unit->id }}"
                                               {{ in_array($unit->id, old('school_units', $userUnits ?? [])) ? 'checked' : '' }}>
                                        <div class="ms-3">
                                            <label class="form-check-label fw-bold text-body mb-0" for="unit-{{ $unit->id }}">
                                                {{ $unit->name }}
                                            </label>
                                            <span class="text-muted d-block small mt-0.5">{{ $unit->level }} &middot; {{ $unit->program?->name }} &middot; Kode {{ $unit->code }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-building-slash fs-3 text-muted mb-2"></i>
                            <p class="text-muted small mb-0">Belum ada unit sekolah. Buat unit terlebih dahulu.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan Pengguna <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

        </form>
    </div>
</div>
@endsection
