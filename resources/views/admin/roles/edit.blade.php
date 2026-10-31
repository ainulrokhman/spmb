@extends('layouts.admin')

@section('title', 'Edit Role')
@section('page-title', 'Edit Role: ' . $role->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}" class="text-decoration-none">Roles</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        
        @if(session('error'))
            <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Role Details</h5>
                    <p class="text-muted small mb-0">Modify basic properties of the role</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Role Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $role->name) }}" 
                               placeholder="e.g. Editor, Moderator" 
                               required 
                               {{ in_array($role->name, ['Admin', 'User']) ? 'readonly' : '' }}
                               autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if(in_array($role->name, ['Admin', 'User']))
                            <div class="form-text text-warning small"><i class="bi bi-info-circle-fill me-1"></i> This is a system-defined role. The name cannot be modified to prevent system breaking.</div>
                        @else
                            <div class="form-text text-muted small">The name must be unique. CamelCase or spaces are allowed.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0 fw-bold text-body">Configure Permissions</h5>
                        <p class="text-muted small mb-0">Assign or revoke specific system abilities</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-2" onclick="toggleAllPermissions(true)">
                            Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="toggleAllPermissions(false)">
                            Deselect All
                        </button>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(count($groupedPermissions) > 0)
                        <div class="row g-4">
                            @foreach($groupedPermissions as $category => $permissions)
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card border border-light h-100 bg-light bg-opacity-30 rounded-3">
                                        <div class="card-header bg-light border-0 py-3 px-3 d-flex align-items-center justify-content-between">
                                            <span class="fw-bold text-body">{{ $category }} Permissions</span>
                                            <div class="form-check form-switch mb-0">
                                                @php
                                                    $categoryPermIds = collect($permissions)->pluck('id')->toArray();
                                                    $hasAllCategoryChecked = count(array_intersect($categoryPermIds, $rolePermissions)) === count($categoryPermIds);
                                                @endphp
                                                <input class="form-check-input group-toggler" 
                                                       type="checkbox" 
                                                       data-category="{{ $category }}" 
                                                       id="toggle-{{ $category }}"
                                                       {{ $hasAllCategoryChecked ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($permissions as $permission)
                                                    @php
                                                        $isChecked = in_array($permission->id, $rolePermissions);
                                                    @endphp
                                                    <div class="form-check d-flex align-items-center">
                                                        <input class="form-check-input permission-checkbox" 
                                                               type="checkbox" 
                                                               name="permissions[]" 
                                                               value="{{ $permission->id }}" 
                                                               id="perm-{{ $permission->id }}"
                                                               data-category="{{ $category }}"
                                                               {{ $isChecked ? 'checked' : '' }}>
                                                        <label class="form-check-label ms-2 small text-secondary" for="perm-{{ $permission->id }}">
                                                            {{ str_replace('-', ' ', $permission->name) }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-shield-slash fs-2 text-muted mb-2"></i>
                            <p class="text-muted small mb-0">No permissions exist in the database.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Save Changes <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Group select/unselect logic
        document.querySelectorAll('.group-toggler').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const category = e.target.getAttribute('data-category');
                const isChecked = e.target.checked;
                
                document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`).forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });
        });

        // Individual checkbox change updates group toggle state
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const category = e.target.getAttribute('data-category');
                const groupCheckbox = document.querySelector(`#toggle-${category}`);
                
                const allInCategory = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`);
                const checkedInCategory = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]:checked`);
                
                groupCheckbox.checked = allInCategory.length === checkedInCategory.length;
            });
        });
    });

    function toggleAllPermissions(checked) {
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
        document.querySelectorAll('.group-toggler').forEach(toggle => {
            toggle.checked = checked;
        });
    }
</script>
@endsection
