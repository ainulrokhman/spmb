@extends('layouts.admin')

@section('title', 'Jalur Pendaftaran')
@section('page-title', 'Manajemen Jalur Pendaftaran')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Jalur Pendaftaran</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Jalur Pendaftaran</h5>
            <p class="text-muted small mb-0">Jalur dimiliki oleh unit; setiap unit mengelola jalurnya sendiri</p>
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">

            <select id="unit-filter" class="form-select bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 rounded-pill" style="width: 220px;">
                <option value="">All Units</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>

            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text"
                       name="search"
                       value=""
                       class="form-control bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 ps-5 rounded-pill"
                       placeholder="Cari jalur pendaftaran..."
                       style="width: 250px;">
                <i class="bi bi-search position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
                <a href="#" id="clear-search" class="position-absolute text-muted hover-text-dark d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>

            @can('admission-track-create')
            <a href="{{ route('admin.admission-tracks.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 py-2 px-3 fw-medium" style="border-radius: 8px;">
                <i class="bi bi bi-signpost-plus fs-5"></i> Tambah Jalur Pendaftaran
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
            <table class="table table-hover align-middle mb-0" id="admission-tracks-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Unit Sekolah</th>
                        <th class="text-secondary fw-semibold">Nama Jalur</th>
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
        const table = $('#admission-tracks-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.admission-tracks.index') }}",
                type: 'GET',
                data: function(d) {
                    d.school_unit_id = $('#unit-filter').val();
                }
            },
            columns: [
                { data: 'unit_name', name: 'school_unit_id' },
                { data: 'name', name: 'name' },
                { data: 'is_active', name: 'is_active', searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, class: 'text-end' }
            ],
            dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"l>rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top"ip>',
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Memuat admission tracks...',
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

        $('#unit-filter').on('change', function() {
            table.ajax.reload();
        });

    });
</script>
@endpush
