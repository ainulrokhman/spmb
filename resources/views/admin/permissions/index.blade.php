@extends('layouts.admin')

@section('title', 'Permissions')
@section('page-title', 'Permission Management')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Permissions</li>
@endsection

@section('content')
<div class="row g-4">
    <!-- Permissions Table List -->
    @can('permission-create')
    <div class="col-12 col-lg-8">
    @else
    <div class="col-12">
    @endcan
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
                <div>
                    <h5 class="mb-0 fw-bold text-body">Available Permissions</h5>
                    <p class="text-muted small mb-0">System capabilities that can be assigned to roles</p>
                </div>
                
                <!-- Search Form -->
                <form id="search-form" class="position-relative" onsubmit="return false;">
                    <input type="text" 
                           name="search" 
                           value="" 
                           class="form-control bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 ps-5 rounded-pill" 
                           placeholder="Search permissions..." 
                           style="width: 220px;">
                    <i class="bi bi-search position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
                    <a href="#" id="clear-search" class="position-absolute text-muted hover-text-dark d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                        <i class="bi bi-x-circle-fill"></i>
                    </a>
                </form>
            </div>
            <div class="card-body px-4 pb-4">
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

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="permissions-table" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th class="text-secondary fw-semibold">Permission Name</th>
                                <th class="text-secondary fw-semibold">Roles Using This</th>
                                <th style="width: 140px;" class="text-secondary fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline Permission Create Form -->
    @can('permission-create')
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Create Permission</h5>
                <p class="text-muted small mb-0">Register a new system action tag</p>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.permissions.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Permission Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}" 
                               placeholder="e.g. publish-article, export-report" 
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">
                            Use lowercase, dashes, and standard action naming conventions (e.g., <code>create-posts</code>, <code>approve-payments</code>).
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-medium d-flex align-items-center justify-content-center gap-2" style="border-radius: 8px;">
                        <i class="bi bi-plus-circle"></i> Add Permission
                    </button>
                </form>

                <div class="mt-4 p-3 bg-light bg-opacity-50 rounded-3 border">
                    <h6 class="fw-bold small text-body mb-2"><i class="bi bi-info-circle-fill text-primary me-1"></i> RBAC Guidelines</h6>
                    <p class="text-muted mb-0 small" style="line-height: 1.4;">
                        Permissions represent fine-grained access rules (e.g. <code>manage-users</code>). In Laravel, you protect controllers and routes by validating these keys. Create them here, then assign them inside Role editing layouts.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endcan
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const table = $('#permissions-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.permissions.index') }}",
                type: 'GET'
            },
            columns: [
                { data: 'permission_name', name: 'name' },
                { data: 'roles_using_this', name: 'roles_using_this', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, class: 'text-end' }
            ],
            dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"l>rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top"ip>',
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading permissions...',
                lengthMenu: "Show _MENU_ entries"
            },
            drawCallback: function() {
                // Ensure page-item and page-link are properly applied
                $('.dataTables_paginate .paginate_button').addClass('page-item');
                $('.dataTables_paginate .paginate_button a').addClass('page-link');
            }
        });

        // Link external search bar
        const $searchInput = $('input[name="search"]');
        const $clearBtn = $('#clear-search');

        $searchInput.on('input', function() {
            if (this.value) {
                $clearBtn.removeClass('d-none');
            } else {
                $clearBtn.addClass('d-none');
            }
            table.search(this.value).draw();
        });

        $clearBtn.on('click', function(e) {
            e.preventDefault();
            $searchInput.val('');
            $clearBtn.addClass('d-none');
            table.search('').draw();
        });
    });
</script>
@endpush
