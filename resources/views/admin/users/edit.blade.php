@extends('layouts.admin')

@section('title', 'Edit User')
@section('page-title', 'Edit User: ' . $user->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-8 mx-auto">
        
        @if(session('error'))
            <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">User Profile Information</h5>
                    <p class="text-muted small mb-0">Update the profile data for this account</p>
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
                                   placeholder="John Doe" 
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
                                   placeholder="johndoe@example.com" 
                                   {{ $user->email === \App\Support\SystemAccess::SUPER_ADMIN_EMAIL ? 'readonly' : '' }}
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($user->email === \App\Support\SystemAccess::SUPER_ADMIN_EMAIL)
                                <div class="form-text text-warning small"><i class="bi bi-info-circle-fill me-1"></i> This is the main system Super Admin account. The email cannot be modified.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Update Credentials</h5>
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
                                   placeholder="Leave empty to keep current">
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

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Assign System Roles</h5>
                    <p class="text-muted small mb-0">Select roles to control user permissions</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(count($roles) > 0)
                        <div class="d-flex flex-column gap-3">
                            @foreach($roles as $role)
                                @php
                                    $isChecked = in_array($role->name, $userRoles);
                                    $isDisabled = ($user->email === \App\Support\SystemAccess::SUPER_ADMIN_EMAIL && $role->name === \App\Support\SystemAccess::SUPER_ADMIN_ROLE);
                                @endphp
                                <div class="card border p-3 rounded-3 bg-light bg-opacity-20">
                                    <div class="form-check d-flex align-items-center mb-0">
                                        <!-- Hidden input if disabled so it still posts in the form -->
                                        @if($isDisabled)
                                            <input type="hidden" name="roles[]" value="{{ \App\Support\SystemAccess::SUPER_ADMIN_ROLE }}">
                                        @endif
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="roles[]" 
                                               value="{{ $role->name }}" 
                                               id="role-{{ $role->id }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               {{ $isDisabled ? 'disabled' : '' }}>
                                        <div class="ms-3">
                                            <label class="form-check-label fw-bold text-body mb-0" for="role-{{ $role->id }}" style="cursor: pointer;">
                                                {{ $role->name }}
                                                @if($isDisabled)
                                                    <span class="badge bg-secondary ms-2 small">System Locked</span>
                                                @endif
                                            </label>
                                            <span class="text-muted d-block small mt-0.5">
                                                @if($role->name === \App\Support\SystemAccess::SUPER_ADMIN_ROLE)
                                                    Full unrestricted system admin access (bypasses all checks).
                                                @elseif($role->name === \App\Support\SystemAccess::ADMIN_ROLE)
                                                    Management access to view dashboard, edit users, and manage roles.
                                                @elseif($role->name === \App\Support\SystemAccess::USER_ROLE)
                                                    Standard user access, limited only to view dashboard details.
                                                @else
                                                    Custom role with specific access privileges.
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
                            <p class="text-muted small mb-0">No roles are created yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
                <a href="{{ route('admin.users.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Save Changes <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

        </form>
    </div>
</div>
@endsection
