@extends('layouts.admin')

@section('title', 'Peran')
@section('page-title', 'Manajemen Peran')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Peran</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Peran yang Tersedia</h5>
            <p class="text-muted small mb-0">Tentukan hak akses dan atur tingkatan pengguna</p>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Search Form -->
            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text" 
                       name="search" 
                       value="" 
                       class="form-control bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 ps-5 rounded-pill" 
                       placeholder="Cari peran..." 
                       style="width: 250px;">
                <i class="bi bi-search position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
                <a href="#" id="clear-search" class="position-absolute text-muted hover-text-dark d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>

            @can('role-create')
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 py-2 px-3 fw-medium" style="border-radius: 8px;">
                <i class="bi bi-shield-plus fs-5"></i> Tambah Peran
            </a>
            @endcan
        </div>
    </div>

    <div class="card-body px-4 pb-4">
        <!-- Alert banners -->
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
            <table class="table table-hover align-middle mb-0" id="roles-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Nama Peran</th>
                        <th class="text-secondary fw-semibold">Jumlah Hak Akses</th>
                        <th class="text-secondary fw-semibold text-center">Pengguna Aktif</th>
                        <th class="text-secondary fw-semibold">Pratinjau Hak Akses</th>
                        <th style="width: 180px;" class="text-secondary fw-semibold text-end">Aksi</th>
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
        const table = $('#roles-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.roles.index') }}",
                type: 'GET'
            },
            columns: [
                { data: 'role_name', name: 'name' },
                { data: 'permissions_count', name: 'permissions_count', searchable: false },
                { data: 'users_count', name: 'users_count', searchable: false, class: 'text-center' },
                { data: 'permissions_preview', name: 'permissions_preview', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, class: 'text-end' }
            ],
            dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"l>rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top"ip>',
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Memuat roles...',
                lengthMenu: "Tampilkan _MENU_ data",
                search: "Cari:",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                emptyTable: "Tidak ada data tersedia",
                zeroRecords: "Tidak ada data yang cocok ditemukan",
                paginate: { first: "Pertama", last: "Terakhir", next: "Berikutnya", previous: "Sebelumnya" }
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
