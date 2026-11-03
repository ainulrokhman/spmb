@extends('layouts.admin')

@section('title', 'Daftar Siswa')
@section('page-title', 'Daftar Siswa')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Daftar Siswa</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Daftar Siswa</h5>
            <p class="text-muted small mb-0">Semua pendaftar dalam semua status pendaftaran, sesuai unit tugas Anda</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select id="unit-filter" aria-label="Filter unit" class="form-select" style="width: 190px;">
                <option value="">Semua Unit</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}" {{ (string) request()->query('school_unit_id') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                @endforeach
            </select>
            <select id="wave-filter" aria-label="Filter gelombang" class="form-select" style="width: 170px;">
                <option value="">Semua Gelombang</option>
                @foreach($waves as $wave)
                    <option value="{{ $wave->id }}" {{ (string) request()->query('admission_wave_id') === (string) $wave->id ? 'selected' : '' }}>{{ $wave->name }} ({{ $wave->academicYear?->name }})</option>
                @endforeach
            </select>
            <select id="track-filter" aria-label="Filter jalur" class="form-select" style="width: 160px;">
                <option value="">Semua Jalur</option>
                @foreach($tracks as $track)
                    <option value="{{ $track->id }}" {{ (string) request()->query('admission_track_id') === (string) $track->id ? 'selected' : '' }}>{{ $track->name }}</option>
                @endforeach
            </select>
            <select id="status-filter" aria-label="Filter status" class="form-select" style="width: 190px;">
                <option value="">Semua Status</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request()->query('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.students.export', request()->query()) }}" id="export-btn" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        </div>
    </div>

    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table id="students-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Siswa</th>
                        <th class="text-secondary fw-semibold">Unit / Gelombang / Jalur</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-secondary fw-semibold">Jurusan</th>
                        <th class="text-secondary fw-semibold">Jender</th>
                        <th class="text-secondary fw-semibold">Wali</th>
                        <th class="text-secondary fw-semibold">Tagihan Lunas</th>
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
        const table = $('#students-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.students.index') }}",
                type: 'GET',
                data: function(d) {
                    d.school_unit_id = $('#unit-filter').val();
                    d.admission_wave_id = $('#wave-filter').val();
                    d.admission_track_id = $('#track-filter').val();
                    d.status = $('#status-filter').val();
                }
            },
            columns: [
                { data: 'student', name: 'child_name', orderable: false },
                { data: 'unit', name: 'school_unit_id', orderable: false },
                { data: 'status_badge', name: 'status', orderable: false },
                { data: 'major_name', name: 'major_id', orderable: false },
                { data: 'gender_label', name: 'gender', orderable: false },
                { data: 'guardian', name: 'user_id', orderable: false },
                { data: 'paid_at', name: 'paid_at', orderable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            order: [[0, 'asc']],
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

        // Keep the active filters in the page URL and in the
        // export link, so the CSV matches what is on screen.
        function syncFilterUrl() {
            const params = new URLSearchParams();
            const pairs = [
                ['school_unit_id', $('#unit-filter').val()],
                ['admission_wave_id', $('#wave-filter').val()],
                ['admission_track_id', $('#track-filter').val()],
                ['status', $('#status-filter').val()]
            ];
            pairs.forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            const query = params.toString();
            window.history.replaceState(null, '', "{{ route('admin.students.index') }}" + (query ? '?' + query : ''));
            $('#export-btn').attr('href', "{{ route('admin.students.export') }}" + (query ? '?' + query : ''));
        }

        syncFilterUrl();

        $('#unit-filter, #wave-filter, #track-filter, #status-filter').on('change', function() {
            syncFilterUrl();
            table.ajax.reload();
        });
    });
</script>
@endpush
