@extends('layouts.admin')

@section('title', 'Edit Permission')
@section('page-title', 'Edit Permission: ' . $permission->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}" class="text-decoration-none">Permissions</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Modify Permission Details</h5>
                <p class="text-muted small mb-0">Update the name key of the system capability</p>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.permissions.update', $permission->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="name" class="form-label fw-semibold text-secondary">Permission Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $permission->name) }}" 
                               placeholder="e.g. publish-article" 
                               required 
                               autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small mt-2">
                            Warning: Changing permission names might break existing <code>@can</code> gates or middleware rules referencing the old name <code>"{{ $permission->name }}"</code>.
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <a href="{{ route('admin.permissions.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                            Save Changes <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
