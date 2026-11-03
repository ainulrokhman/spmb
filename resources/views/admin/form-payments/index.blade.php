@extends('layouts.admin')

@section('title', 'Pembayaran Formulir')
@section('page-title', 'Pembayaran Formulir')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Pembayaran Formulir</li>
@endsection

@section('content')
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Pembayaran Formulir</h5>
            <p class="text-muted small mb-0">Semua pendaftaran dalam semua kondisi biaya formulir. Pengajuan keringanan dan pembayaran yang menunggu konfirmasi tampil paling atas.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select id="unit-filter" aria-label="Filter unit" class="form-select" style="width: 180px;">
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
            <select id="fee-status-filter" aria-label="Filter status biaya" class="form-select" style="width: 180px;">
                <option value="">Semua Status Biaya</option>
                @foreach($feeStatuses as $key => $label)
                    <option value="{{ $key }}" {{ request()->query('form_fee_status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select id="channel-filter" aria-label="Filter kanal" class="form-select" style="width: 140px;">
                <option value="">Semua Kanal</option>
                @foreach($channels as $key => $label)
                    <option value="{{ $key }}" {{ request()->query('channel') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table id="form-payments-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Pendaftar</th>
                        <th class="text-secondary fw-semibold">Unit / Gelombang / Jalur</th>
                        <th class="text-secondary fw-semibold">Wali</th>
                        <th class="text-secondary fw-semibold">Biaya</th>
                        <th class="text-secondary fw-semibold">Diskon</th>
                        <th class="text-secondary fw-semibold">Dibayar</th>
                        <th class="text-secondary fw-semibold">Status Biaya</th>
                        <th class="text-secondary fw-semibold">Pembayaran</th>
                        <th class="text-secondary fw-semibold text-end">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- Shared relief modal: filled by the row's Keringanan button (decide a pending request, or grant directly). --}}
<div class="modal fade" id="relief-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="relief-form" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="relief-title">Keringanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="relief-context"></p>
                <div id="relief-attachment-wrap" class="mb-3 d-none">
                    <a id="relief-attachment" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-paperclip"></i> Lihat Lampiran Pengajuan</a>
                </div>
                <div class="mb-3">
                    <label for="relief-type" class="form-label fw-semibold text-secondary">Bentuk Keringanan</label>
                    <select name="relief_type" id="relief-type" class="form-select">
                        <option value="waiver">Bebaskan penuh</option>
                        <option value="percent">Diskon %</option>
                        <option value="nominal">Diskon Rp</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="relief-value" class="form-label fw-semibold text-secondary">Nilai Diskon</label>
                    <input type="number" name="relief_value" id="relief-value" class="form-control" min="1" placeholder="Isi untuk diskon % atau Rp; kosongkan bila bebaskan penuh">
                </div>
                <div class="mb-1">
                    <label for="relief-note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                    <input type="text" name="note" id="relief-note" class="form-control" maxlength="500" placeholder="Opsional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="decision" value="reject" id="relief-reject" class="btn btn-outline-danger" onclick="return confirm('Tolak pengajuan ini?');">Tolak</button>
                <button type="submit" name="decision" value="approve" id="relief-approve" class="btn btn-success">Setujui</button>
                <button type="submit" id="relief-grant" class="btn btn-primary">Beri Keringanan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const table = $('#form-payments-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.form-payments.index') }}",
                type: 'GET',
                data: function(d) {
                    d.school_unit_id = $('#unit-filter').val();
                    d.admission_wave_id = $('#wave-filter').val();
                    d.form_fee_status = $('#fee-status-filter').val();
                    d.channel = $('#channel-filter').val();
                }
            },
            columns: [
                { data: 'applicant', name: 'child_name', orderable: false },
                { data: 'unit', name: 'school_unit_id', orderable: false },
                { data: 'guardian', name: 'user_id', orderable: false },
                { data: 'fee', name: 'form_fee_amount', orderable: false },
                { data: 'discount', name: 'discount', orderable: false },
                { data: 'payable', name: 'payable', orderable: false },
                { data: 'fee_status', name: 'form_fee_status', orderable: false },
                { data: 'payment_info', name: 'payment_info', orderable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            order: [],
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

        // Keep the active filters in the page URL.
        function syncFilterUrl() {
            const params = new URLSearchParams();
            const pairs = [
                ['school_unit_id', $('#unit-filter').val()],
                ['admission_wave_id', $('#wave-filter').val()],
                ['form_fee_status', $('#fee-status-filter').val()],
                ['channel', $('#channel-filter').val()]
            ];
            pairs.forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            const query = params.toString();
            window.history.replaceState(null, '', "{{ route('admin.form-payments.index') }}" + (query ? '?' + query : ''));
        }

        syncFilterUrl();

        $('#unit-filter, #wave-filter, #fee-status-filter, #channel-filter').on('change', function() {
            syncFilterUrl();
            table.ajax.reload();
        });

        // Relief modal: one shared form, filled from the row
        // button that opened it (decide vs. grant).
        const reliefModalEl = document.getElementById('relief-modal');
        const reliefForm = document.getElementById('relief-form');
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.relief-action');
            if (!button) {
                return;
            }

            const isDecide = button.dataset.mode === 'decide';
            reliefForm.action = button.dataset.url;
            document.getElementById('relief-title').textContent = isDecide
                ? 'Putuskan Pengajuan Keringanan'
                : 'Beri Keringanan Langsung';
            document.getElementById('relief-context').textContent = isDecide
                ? 'Pengajuan keringanan biaya formulir untuk ' + button.dataset.child + '. Alasan wali: ' + (button.dataset.reason || '-')
                : 'Berikan keringanan biaya formulir untuk ' + button.dataset.child + ' tanpa pengajuan dari wali.';

            const attachmentWrap = document.getElementById('relief-attachment-wrap');
            if (isDecide && button.dataset.attachment) {
                document.getElementById('relief-attachment').href = button.dataset.attachment;
                attachmentWrap.classList.remove('d-none');
            } else {
                attachmentWrap.classList.add('d-none');
            }

            // Only the buttons of the active mode may submit:
            // hidden ones are disabled so Enter cannot fire them.
            [['relief-reject', isDecide], ['relief-approve', isDecide], ['relief-grant', !isDecide]].forEach(([id, active]) => {
                const el = document.getElementById(id);
                el.classList.toggle('d-none', !active);
                el.disabled = !active;
            });

            document.getElementById('relief-type').value = 'waiver';
            document.getElementById('relief-value').value = '';
            document.getElementById('relief-note').value = '';

            new bootstrap.Modal(reliefModalEl).show();
        });
    });
</script>
@endpush
