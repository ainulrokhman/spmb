@extends('layouts.admin')

@section('title', 'Broadcast Baru')
@section('page-title', 'Broadcast Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.broadcasts.index') }}">Broadcast</a></li>
    <li class="breadcrumb-item active" aria-current="page">Baru</li>
@endsection

@section('content')
@if(session('error'))
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.whatsapp.broadcasts.store') }}" method="POST">
    @csrf
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold text-body"><i class="bi bi-funnel me-2"></i>Target Penerima</h5>
            <p class="text-muted small mb-0">Broadcast selalu untuk satu unit. Saring lebih lanjut dengan gelombang, status pendaftaran, dan status pembayaran bila perlu.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="school_unit_id" class="form-label">Unit Sekolah <span class="text-danger">*</span></label>
                    <select name="school_unit_id" id="school_unit_id" class="form-select" required>
                        <option value="">— Pilih unit —</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('school_unit_id') == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="admission_wave_id" class="form-label">Gelombang</label>
                    <select name="admission_wave_id" id="admission_wave_id" class="form-select">
                        <option value="">Semua gelombang</option>
                        @foreach($waves as $wave)
                            <option value="{{ $wave->id }}" data-unit="{{ $wave->school_unit_id }}" @selected(old('admission_wave_id') == $wave->id)>{{ $wave->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="application_status" class="form-label">Status Pendaftaran</label>
                    <select name="application_status" id="application_status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('application_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="payment_filter" class="form-label">Status Pembayaran</label>
                    <select name="payment_filter" id="payment_filter" class="form-select">
                        <option value="">Semua</option>
                        @foreach($paymentFilters as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_filter') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="button" id="preview-button" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Muat Pratinjau Penerima</button>
                <span id="preview-summary" class="text-muted small ms-2"></span>
            </div>
            <div id="preview-wrap" class="mt-3 d-none">
                <p class="text-muted small">Hilangkan centang pada anak yang tidak ingin dikirimi. Bila pratinjau tidak dimuat, broadcast dikirim ke semua penerima hasil filter.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all" class="form-check-input" checked></th>
                                <th>Anak</th>
                                <th>Wali</th>
                                <th>Nomor WhatsApp</th>
                            </tr>
                        </thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold text-body"><i class="bi bi-chat-text me-2"></i>Isi Pesan</h5>
            <p class="text-muted small mb-0">
                Variabel:
                @foreach($variables as $name => $label)
                    <code>{{ '{'.$name.'}' }}</code> ({{ $label }})@if(! $loop->last), @endif
                @endforeach
                <br>Spintax <code>{opsi A|opsi B}</code> dipilih acak per penerima supaya pesan tidak identik persis.
            </p>
        </div>
        <div class="card-body px-4 pb-4">
            @if($templates !== [])
                <div class="mb-3">
                    <label for="template-picker" class="form-label">Ambil dari Template</label>
                    <select id="template-picker" class="form-select">
                        <option value="">— Tulis sendiri —</option>
                        @foreach($templates as $type => $template)
                            <option value="{{ $type }}">{{ $template['label'] }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Isi template disalin ke kotak pesan; variabel di luar daftar di atas perlu Anda sesuaikan sendiri.</div>
                </div>
            @endif
            <textarea name="message" id="message" rows="6" class="form-control" required maxlength="2000" placeholder="Contoh: Assalamu'alaikum Bapak/Ibu {nama_wali}, ...">{{ old('message') }}</textarea>
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label for="delay_min" class="form-label">Jeda minimum (detik)</label>
                    <input type="number" name="delay_min" id="delay_min" class="form-control" value="{{ old('delay_min', 5) }}" min="0" max="120" required>
                </div>
                <div class="col-md-3">
                    <label for="delay_max" class="form-label">Jeda maksimum (detik)</label>
                    <input type="number" name="delay_max" id="delay_max" class="form-control" value="{{ old('delay_max', 15) }}" min="0" max="300" required>
                    <div class="form-text">Jeda antar pesan dipilih acak di antara keduanya.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body px-4 py-4">
            <div class="form-check mb-3">
                <input type="checkbox" name="confirm" id="confirm" value="1" class="form-check-input" required>
                <label for="confirm" class="form-check-label">Saya sudah memeriksa isi pesan dan target penerima broadcast ini.</label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-megaphone me-1"></i>Buat Broadcast</button>
                <a href="{{ route('admin.whatsapp.broadcasts.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
            <p class="text-muted small mt-3 mb-0">Broadcast tersimpan dulu sebagai draf; pengiriman baru berjalan setelah Anda menekan Mulai Kirim di halaman detail, dan membutuhkan queue worker yang berjalan di server.</p>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const waves = @json($waves->map(fn ($wave) => ['id' => $wave->id, 'unit' => $wave->school_unit_id, 'name' => $wave->name])->values());
    const templates = @json($templates);
    const unitSelect = document.getElementById('school_unit_id');
    const waveSelect = document.getElementById('admission_wave_id');

    function refreshWaves() {
        const unitId = unitSelect.value;
        const current = waveSelect.value;
        waveSelect.innerHTML = '<option value="">Semua gelombang</option>';
        waves.filter((wave) => String(wave.unit) === String(unitId)).forEach((wave) => {
            const option = document.createElement('option');
            option.value = wave.id;
            option.textContent = wave.name;
            if (String(wave.id) === String(current)) option.selected = true;
            waveSelect.appendChild(option);
        });
    }
    unitSelect.addEventListener('change', refreshWaves);
    refreshWaves();

    const picker = document.getElementById('template-picker');
    if (picker) {
        picker.addEventListener('change', () => {
            if (picker.value && templates[picker.value]) {
                document.getElementById('message').value = templates[picker.value].content;
            }
        });
    }

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    document.getElementById('preview-button').addEventListener('click', async () => {
        const summary = document.getElementById('preview-summary');
        const body = document.getElementById('preview-body');
        const wrap = document.getElementById('preview-wrap');
        if (!unitSelect.value) {
            summary.textContent = 'Pilih unit sekolah dulu.';
            return;
        }
        summary.textContent = 'Memuat...';
        const response = await fetch('{{ route('admin.whatsapp.broadcasts.preview') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
            body: JSON.stringify({
                school_unit_id: unitSelect.value,
                admission_wave_id: waveSelect.value || null,
                application_status: document.getElementById('application_status').value || null,
                payment_filter: document.getElementById('payment_filter').value || null,
            }),
        });
        if (!response.ok) {
            summary.textContent = 'Pratinjau gagal dimuat.';
            return;
        }
        const data = await response.json();
        summary.textContent = data.count + ' penerima cocok.';
        body.innerHTML = '';
        data.recipients.forEach((recipient) => {
            const row = document.createElement('tr');
            row.innerHTML = '<td><input type="checkbox" class="form-check-input" name="application_ids[]" value="' + recipient.id + '" checked></td>'
                + '<td></td><td></td><td></td>';
            row.children[1].textContent = recipient.child_name;
            row.children[2].textContent = recipient.guardian_name || '';
            row.children[3].textContent = recipient.phone || '';
            body.appendChild(row);
        });
        wrap.classList.remove('d-none');
    });

    document.getElementById('check-all').addEventListener('change', (event) => {
        document.querySelectorAll('input[name="application_ids[]"]').forEach((box) => { box.checked = event.target.checked; });
    });
</script>
@endpush
