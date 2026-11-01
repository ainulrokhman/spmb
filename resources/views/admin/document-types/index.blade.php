@extends('layouts.admin')

@section('title', 'Jenis Berkas')
@section('page-title', 'Manajemen Jenis Berkas')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Jenis Berkas</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Jenis Berkas</h5>
            <p class="text-muted small mb-0">Kolam bersama jenis berkas; setiap unit menentukan mana yang diwajibkan</p>
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text"
                       name="search"
                       value=""
                       class="form-control"
                       placeholder="Cari jenis berkas..."
                       style="width: 250px;">
                <a href="#" id="clear-search" class="position-absolute text-muted d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>

            @can('document-type-create')
            <a href="{{ route('admin.document-types.create') }}" class="btn btn-primary">
                <i class="bi bi-file-earmark-plus fs-5"></i> Tambah Jenis Berkas
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
                        <th class="text-secondary fw-semibold">Nama</th>
                        <th class="text-secondary fw-semibold">Kode</th>
                        <th class="text-secondary fw-semibold">Ekstensi yang Diizinkan</th>
                        <th class="text-secondary fw-semibold">Ukuran Maks</th>
                        <th class="text-secondary fw-semibold">Status</th>
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
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Memuat jenis berkas...',
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
