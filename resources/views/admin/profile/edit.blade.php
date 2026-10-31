@extends('layouts.admin')

@section('title', 'My Profile')
@section('page-title', 'My Profile Settings')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        
        @if(session('success'))
            <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                <!-- Left Column: Avatar Settings -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm text-center p-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3 text-body">Profile Picture</h5>
                            
                            <div class="mb-4 position-relative d-inline-block">
                                <div id="avatar-preview-container">
                                    @if($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" 
                                             id="avatar-preview" 
                                             class="rounded-circle shadow-sm border" 
                                             style="width: 140px; height: 140px; object-fit: cover;" 
                                             alt="Avatar">
                                    @else
                                        @php
                                            $initials = collect(explode(' ', $user->name))
                                                ->map(fn($segment) => mb_substr($segment, 0, 1))
                                                ->take(2)
                                                ->join('');
                                        @endphp
                                        <div id="avatar-initials" 
                                             class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold mx-auto border" 
                                             style="width: 140px; height: 140px; font-size: 3rem;">
                                            {{ strtoupper($initials) }}
                                        </div>
                                        <img src="" 
                                             id="avatar-preview" 
                                             class="rounded-circle shadow-sm border d-none" 
                                             style="width: 140px; height: 140px; object-fit: cover;" 
                                             alt="Avatar">
                                    @endif
                                </div>
                            </div>

                            <p class="text-muted small mb-4">Upload a high-quality JPG, PNG, or WEBP image. Max size: 2MB.</p>
                            
                            <div>
                                <label for="avatar" class="btn btn-outline-primary btn-sm px-3 py-2 fw-medium" style="border-radius: 8px; cursor: pointer;">
                                    <i class="bi bi-camera me-1"></i> Choose New Photo
                                </label>
                                <input type="file" 
                                       class="d-none @error('avatar') is-invalid @enderror" 
                                       id="avatar" 
                                       name="avatar" 
                                       accept="image/*">
                                @error('avatar')
                                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Profile & Credentials -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0 fw-bold text-body">Account Information</h5>
                            <p class="text-muted small mb-0">Update your core profile information</p>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="row g-3">
                                <!-- Name Field -->
                                <div class="col-12">
                                    <label for="name" class="form-label fw-semibold text-secondary">Full Name</label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $user->name) }}" 
                                           placeholder="Enter name" 
                                           required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Email Field -->
                                <div class="col-12">
                                    <label for="email" class="form-label fw-semibold text-secondary">Email Address</label>
                                    <input type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           id="email" 
                                           name="email" 
                                           value="{{ old('email', $user->email) }}" 
                                           placeholder="Enter email" 
                                           {{ $user->email === \App\Support\SystemAccess::SUPER_ADMIN_EMAIL ? 'readonly' : '' }}
                                           required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($user->email === \App\Support\SystemAccess::SUPER_ADMIN_EMAIL)
                                        <div class="form-text text-warning small">
                                            <i class="bi bi-info-circle-fill me-1"></i> This is the main seed Super Admin account. Email modifications are disabled.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4">
                            <h5 class="mb-0 fw-bold text-body">Change Password</h5>
                            <p class="text-muted small mb-0">Leave password fields blank to keep current credentials unchanged</p>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="row g-3">
                                <!-- Password Field -->
                                <div class="col-12 col-md-6">
                                    <label for="password" class="form-label fw-semibold text-secondary">New Password</label>
                                    <input type="password" 
                                           class="form-control @error('password') is-invalid @enderror" 
                                           id="password" 
                                           name="password" 
                                           placeholder="At least 8 characters">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Password Confirmation -->
                                <div class="col-12 col-md-6">
                                    <label for="password_confirmation" class="form-label fw-semibold text-secondary">Confirm New Password</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password_confirmation" 
                                           name="password_confirmation" 
                                           placeholder="Repeat new password">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
                        <a href="/admin" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                            Save Settings <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const avatarInput = document.getElementById('avatar');
        const avatarPreview = document.getElementById('avatar-preview');
        const avatarInitials = document.getElementById('avatar-initials');

        if (avatarInput) {
            avatarInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        avatarPreview.src = e.target.result;
                        avatarPreview.classList.remove('d-none');
                        if (avatarInitials) {
                            avatarInitials.classList.add('d-none');
                        }
                    };

                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        }
    });
</script>
@endpush
