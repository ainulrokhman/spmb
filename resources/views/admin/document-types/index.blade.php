@extends('layouts.admin')

@section('title', 'Document Types')
@section('page-title', 'Document Type Management')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Document Types</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Document Types</h5>
            <p class="text-muted small mb-0">Shared pool of document types; each unit decides which ones it requires</p>
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text"
                       name="search"
                       value=""
                       class="form-control bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 ps-5 rounded-pill"
                       placeholder="Search document types..."
                       style="width: 250px;">
                <i class="bi bi-search position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
                <a href="#" id="clear-search" class="position-absolute text-muted hover-text-dark d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>

            @can('document-type-create')
            <a href="{{ route('admin.document-types.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 py-2 px-3 fw-medium" style="border-radius: 8px;">
                <i class="bi bi-file-earmark-plus fs-5"></i> Create New Document Type
            </a>
            @endcan
        </div>
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
            <table class="table table-hover align-middle mb-0" id="document-types-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Name</th>
                        <th class="text-secondary fw-semibold">Code</th>
                        <th class="text-secondary fw-semibold">Allowed Extensions</th>
                        <th class="text-secondary fw-semibold">Max Size</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th style="width: 180px;" class="text-secondary fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const table = $('#document-types-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.document-types.index') }}",
                type: 'GET'
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'code', name: 'code' },
                { data: 'allowed_extensions', name: 'allowed_extensions' },
                { data: 'max_size_kb', name: 'max_size_kb', searchable: false },
                { data: 'is_active', name: 'is_active', searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, class: 'text-end' }
            ],
            dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"l>rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top"ip>',
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading document types...',
                lengthMenu: "Show _MENU_ entries"
            },
            drawCallback: function() {
                $('.dataTables_paginate .paginate_button').addClass('page-item');
                $('.dataTables_paginate .paginate_button a').addClass('page-link');
            }
        });

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
