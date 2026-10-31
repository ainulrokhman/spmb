@extends('layouts.admin')

@section('title', 'Add User')
@section('page-title', 'Add New User')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-8 mx-auto">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">User Profile Information</h5>
                    <p class="text-muted small mb-0">Fill in the basic profile data for the new account</p>
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
                            <label for="email" class="form-label fw-semibold text-secondary">Email Address</label>
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
                    <h5 class="mb-0 fw-bold text-body">Security Credentials</h5>
                    <p class="text-muted small mb-0">Configure user security password</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row g-3">
                        <!-- Password Field -->
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label fw-semibold text-secondary">Password</label>
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
                            <label for="password_confirmation" class="form-label fw-semibold text-secondary">Confirm Password</label>
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
                    <h5 class="mb-0 fw-bold text-body">Assign System Roles</h5>
                    <p class="text-muted small mb-0">Attach roles to define the user access permissions</p>
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
                                            <label class="form-check-label fw-bold text-body mb-0" for="role-{{ $role->id }}" style="cursor: pointer;">
                                                {{ $role->name }}
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
                            <p class="text-muted small mb-0">No roles are created yet. Please create roles first.</p>
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
                    Save User <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

        </form>
    </div>
</div>
@endsection
