@extends('layouts.admin')

@section('title', 'Verifikasi Pendaftaran')
@section('page-title', 'Verifikasi Pendaftaran')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Verifikasi Pendaftaran</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Verifikasi Pendaftaran</h5>
            <p class="text-muted small mb-0">Pendaftar yang sudah mengirim pendaftaran, sesuai unit tugas Anda</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select id="unit-filter" aria-label="Filter unit" class="form-select" style="width: 190px;">
                <option value="">Semua Unit</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            <select id="wave-filter" aria-label="Filter gelombang" class="form-select" style="width: 170px;">
                <option value="">Semua Gelombang</option>
                @foreach($waves as $wave)
                    <option value="{{ $wave->id }}">{{ $wave->name }} ({{ $wave->academicYear?->name }})</option>
                @endforeach
            </select>
            <select id="track-filter" aria-label="Filter jalur" class="form-select" style="width: 160px;">
                <option value="">Semua Jalur</option>
                @foreach($tracks as $track)
                    <option value="{{ $track->id }}">{{ $track->name }}</option>
                @endforeach
            </select>
            <select id="status-filter" aria-label="Filter status" class="form-select" style="width: 180px;">
                <option value="">Semua Status</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text" aria-label="Cari pendaftar" name="search" value="" class="form-control" placeholder="Cari nama / nomor..." style="width: 210px;">
                <a href="#" id="clear-search" class="position-absolute text-muted d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>
        </div>
    </div>

    <div class="card-body px-4 pb-4">
        @if(session('success'))
            <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        <div class="table-responsive">
            <table id="applications-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Nomor</th>
                        <th class="text-secondary fw-semibold">Anak / Wali</th>
                        <th class="text-secondary fw-semibold">Unit / Gelombang / Jalur</th>
                        <th class="text-secondary fw-semibold">Biaya Formulir</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-secondary fw-semibold">Dikirim</th>
                        <th class="text-secondary fw-semibold text-end">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const table = $('#applications-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.applications.index') }}",
                type: 'GET',
                data: function(d) {
                    d.school_unit_id = $('#unit-filter').val();
                    d.admission_wave_id = $('#wave-filter').val();
                    d.admission_track_id = $('#track-filter').val();
                    d.status = $('#status-filter').val();
                }
            },
            columns: [
                { data: 'registration_number', name: 'registration_number' },
                { data: 'applicant', name: 'child_name', orderable: false },
                { data: 'unit', name: 'school_unit_id', orderable: false },
                { data: 'fee', name: 'form_fee_status', orderable: false },
                { data: 'status_badge', name: 'status', orderable: false },
                { data: 'submitted_at', name: 'submitted_at' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            order: [[5, 'desc']],
            language: {
                processing: "Memuat...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                zeroRecords: "Tidak ada data yang cocok ditemukan",
                emptyTable: "Tidak ada data tersedia di tabel",
                paginate: {
                    first: "Pertama",
                    previous: "Sebelumnya",
                    next: "Berikutnya",
                    last: "Terakhir"
                }
            }
        });

        $('#unit-filter, #wave-filter, #track-filter, #status-filter').on('change', function() {
            table.ajax.reload();
        });

        let searchTimeout;
        $('#search-form input[name="search"]').on('keyup', function() {
            clearTimeout(searchTimeout);
            const value = $(this).val();
            $('#clear-search').toggleClass('d-none', value.length === 0);
            searchTimeout = setTimeout(() => table.search(value).draw(), 300);
        });

        $('#clear-search').on('click', function(e) {
            e.preventDefault();
            $('#search-form input[name="search"]').val('');
            $(this).addClass('d-none');
            table.search('').draw();
        });
    });
</script>
@endpush
