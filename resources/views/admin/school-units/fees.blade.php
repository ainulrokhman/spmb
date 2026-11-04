@extends('layouts.admin')

@section('title', 'Pemetaan Biaya')
@section('page-title', 'Pemetaan Biaya: ' . $schoolUnit->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">School Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">Fee Mappings</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-9 mx-auto">
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

        {{-- The mode explanations live here, once, at the top of the page — outside the component cards. --}}
        <div class="mb-4">
            <h5 class="mb-0 fw-bold text-body">Pemetaan Biaya — {{ $schoolUnit->name }}</h5>
            <p class="text-muted small mb-2">{{ $schoolUnit->level }} &middot; {{ $schoolUnit->program?->name }}</p>
            <div class="small">
                <span class="fw-semibold">Cara kerja halaman ini:</span>
                <ol class="mb-2 ps-3">
                    <li>Setiap komponen biaya memiliki <strong>kartunya sendiri</strong>: atur jenis, saklar aktif, minimum, dan nominalnya pada kartu itu, lalu tekan <strong>Simpan</strong> di kaki kartu. Kartu lain tidak ikut berubah.</li>
                    <li>Periksa hasilnya pada kartu <strong>Contoh Tagihan</strong> paling bawah sebelum membuka pendaftaran (contoh diperbarui otomatis setiap Anda menyimpan).</li>
                </ol>
                <span class="text-muted">Arti jenis bagi wali — <strong>Tetap</strong>: semua wali membayar nominal yang sama persis seperti yang ditentukan sekolah (contoh: DU Rp 300.000 untuk semua anak). <strong>Pilihan</strong>: sekolah menyediakan beberapa tingkat nominal dan wali memilih satu (contoh: SPI Rp 1 juta / 2 juta / 3 juta). <strong>Bebas</strong>: wali mengisi nominal sendiri, misalnya INFAQ; sekolah hanya dapat menentukan batas minimumnya. <strong>Tidak Dipakai</strong>: komponen dihapus dari unit ini beserta nominalnya. Menonaktifkan komponen lewat saklar <strong>Aktif</strong> berbeda: nominal tetap tersimpan, tetapi komponen tidak ditagihkan selama nonaktif.</span>
            </div>
        </div>

        @forelse($components as $component)
            @php($mapping = $mappings->get($component->id))
            @php($currentMode = $mapping?->mode ?? 'unused')
            <form class="fee-card-form" method="POST" action="{{ route('admin.school-units.fees.card', [$schoolUnit, $component]) }}">
                @csrf
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="aktif-{{ $component->id }}"
                                       name="is_active" value="1" {{ ($mapping?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="aktif-{{ $component->id }}">Aktif</label>
                            </div>
                            <div>
                                <span class="fw-bold text-body">{{ $component->name }}</span>
                                <span class="badge bg-secondary nonaktif-badge {{ $mapping && ! $mapping->is_active ? '' : 'd-none' }}">Nonaktif</span>
                                <span class="text-muted small d-block">{{ $component->code }} &middot; <span class="amounts-count">{{ $mapping?->amounts_count ?? 0 }}</span> nominal/tingkat</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1" for="mode-{{ $component->id }}">Jenis</label>
                            <select name="mode" id="mode-{{ $component->id }}" class="form-select mode-select" style="width: 180px;" aria-label="Jenis untuk {{ $component->name }}">
                                <option value="unused" {{ $currentMode === 'unused' ? 'selected' : '' }}>Tidak Dipakai</option>
                                @foreach(\App\Models\Fee::MODES as $mode)
                                    <option value="{{ $mode }}" {{ $currentMode === $mode ? 'selected' : '' }}>{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mode] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="card-body px-4 pb-4">
                        <div class="min-group mb-3 {{ $currentMode === 'bebas' ? '' : 'd-none' }}">
                            <label class="form-label small fw-semibold mb-1" for="min-{{ $component->id }}">Nominal Minimum</label>
                            <input type="number" min="0" step="1" id="min-{{ $component->id }}" name="min_amount"
                                   value="{{ $mapping?->min_amount }}" class="form-control" style="max-width: 220px;" placeholder="Rp">
                            <div class="form-text">Wali mengisi nominalnya sendiri; nilai ini menjadi batas minimumnya. Kosongkan bila tanpa minimum.</div>
                        </div>

                        <div class="amounts-editor {{ in_array($currentMode, ['fixed', 'pilihan'], true) ? '' : 'd-none' }}">
                            <input type="hidden" name="sync_amounts" value="1">
                            @if($mapping && $mapping->amounts_count === 0)
                                <p class="small text-warning fw-semibold mb-2"><i class="bi bi-exclamation-triangle"></i> Belum ada nominal — komponen ini belum dapat ditagihkan.</p>
                            @endif
                            <div class="amounts-rows">
                                @if($mapping)
                                    @foreach($mapping->amounts as $amount)
                                        <div class="amount-row d-flex align-items-end gap-2 flex-wrap mb-2">
                                            <input type="hidden" name="amounts[{{ $loop->index }}][id]" value="{{ $amount->id }}">
                                            <div>
                                                <label class="form-label small mb-1">Nominal (Rp)</label>
                                                <input type="number" name="amounts[{{ $loop->index }}][amount]" class="form-control" style="width: 160px;" min="0" step="1" required value="{{ $amount->amount }}">
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">Jalur</label>
                                                <select name="amounts[{{ $loop->index }}][admission_track_id]" class="form-select" style="width: 160px;">
                                                    <option value="">Semua Jalur</option>
                                                    @foreach($tracks as $track)
                                                        <option value="{{ $track->id }}" {{ $amount->admission_track_id === $track->id ? 'selected' : '' }}>{{ $track->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">Jenis Kelamin</label>
                                                <select name="amounts[{{ $loop->index }}][gender]" class="form-select" style="width: 150px;">
                                                    <option value="">Semua Jenis Kelamin</option>
                                                    @foreach(\App\Models\FeeAmount::GENDERS as $genderCode => $genderLabel)
                                                        <option value="{{ $genderCode }}" {{ $amount->gender === $genderCode ? 'selected' : '' }}>{{ $genderLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">Status Anak</label>
                                                <select name="amounts[{{ $loop->index }}][child_status_id]" class="form-select" style="width: 160px;">
                                                    <option value="">Semua Status</option>
                                                    @foreach($childStatuses as $status)
                                                        <option value="{{ $status->id }}" {{ $amount->child_status_id === $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">Urutan</label>
                                                <input type="number" name="amounts[{{ $loop->index }}][sort_order]" class="form-control" style="width: 90px;" min="0" step="1" value="{{ $amount->sort_order }}">
                                            </div>
                                            <button type="button" class="btn btn-light border row-remove" title="Hapus baris ini"><i class="bi bi-trash text-danger"></i></button>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary row-add"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
                            <p class="text-muted small mt-2 mb-0">Aturan paling spesifik yang menang: status anak + jalur + jenis kelamin, lalu kombinasi yang lebih sempit, terakhir global (semua cakupan dikosongkan). Untuk Pilihan, setiap baris adalah satu tingkat yang dapat dipilih wali. Baris yang dihapus akan hilang setelah Anda menekan Simpan.</p>
                        </div>

                        <p class="unused-hint text-muted small mb-0 {{ $currentMode === 'unused' ? '' : 'd-none' }}">Komponen ini tidak dipakai di unit ini dan tidak muncul di tagihan. Pilih jenis Tetap, Pilihan, atau Bebas, lalu tekan Simpan untuk memakainya.</p>
                    </div>

                    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-2 px-4">
                        <span class="save-feedback small"></span>
                        <button type="submit" class="btn btn-primary save-btn">Simpan <i class="bi bi-check-lg ms-1"></i></button>
                    </div>
                </div>
            </form>
        @empty
            <p class="text-muted mb-4">Belum ada komponen biaya aktif. Buat komponen terlebih dahulu pada menu Komponen Biaya.</p>
        @endforelse

        {{-- Blank amount row, cloned by the Tambah Baris button of any card. --}}
        <template id="amount-row-template">
            <div class="amount-row d-flex align-items-end gap-2 flex-wrap mb-2">
                <input type="hidden" name="amounts[__INDEX__][id]" value="">
                <div>
                    <label class="form-label small mb-1">Nominal (Rp)</label>
                    <input type="number" name="amounts[__INDEX__][amount]" class="form-control" style="width: 160px;" min="0" step="1" required value="">
                </div>
                <div>
                    <label class="form-label small mb-1">Jalur</label>
                    <select name="amounts[__INDEX__][admission_track_id]" class="form-select" style="width: 160px;">
                        <option value="">Semua Jalur</option>
                        @foreach($tracks as $track)
                            <option value="{{ $track->id }}">{{ $track->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1">Jenis Kelamin</label>
                    <select name="amounts[__INDEX__][gender]" class="form-select" style="width: 150px;">
                        <option value="">Semua Jenis Kelamin</option>
                        @foreach(\App\Models\FeeAmount::GENDERS as $genderCode => $genderLabel)
                            <option value="{{ $genderCode }}">{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1">Status Anak</label>
                    <select name="amounts[__INDEX__][child_status_id]" class="form-select" style="width: 160px;">
                        <option value="">Semua Status</option>
                        @foreach($childStatuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1">Urutan</label>
                    <input type="number" name="amounts[__INDEX__][sort_order]" class="form-control" style="width: 90px;" min="0" step="1" value="0">
                </div>
                <button type="button" class="btn btn-light border row-remove" title="Hapus baris ini"><i class="bi bi-trash text-danger"></i></button>
            </div>
        </template>

        <div class="card border-0 shadow-sm mb-4" id="preview-card">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Contoh Tagihan — {{ $schoolUnit->name }}</h5>
                <p class="text-muted small mb-0">Pratinjau tagihan daftar ulang untuk satu siswa dengan kombinasi di bawah ini, dihitung dari pemetaan yang <strong>aktif</strong>. Hanya komponen ber-mode Tetap yang dijumlahkan; komponen Pilihan menampilkan tingkatnya dan komponen Bebas mengikuti isian wali.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.school-units.fees.edit', $schoolUnit) }}" method="GET" class="d-flex align-items-end gap-2 flex-wrap mb-3">
                    <div>
                        <label class="form-label small fw-semibold mb-1" for="preview-track">Jalur</label>
                        <select id="preview-track" name="preview_track_id" class="form-select">
                            <option value="">Semua Jalur</option>
                            @foreach($tracks as $track)
                                <option value="{{ $track->id }}" {{ $previewTrackId === $track->id ? 'selected' : '' }}>{{ $track->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold mb-1" for="preview-gender">Jenis Kelamin</label>
                        <select id="preview-gender" name="preview_gender" class="form-select">
                            @foreach(\App\Models\FeeAmount::GENDERS as $genderCode => $genderLabel)
                                <option value="{{ $genderCode }}" {{ $previewGender === $genderCode ? 'selected' : '' }}>{{ $genderLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold mb-1" for="preview-status">Status Anak</label>
                        <select id="preview-status" name="preview_child_status_id" class="form-select">
                            <option value="">Ikuti bawaan (Reguler)</option>
                            @foreach($childStatuses as $status)
                                <option value="{{ $status->id }}" {{ $previewStatusId === $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Lihat Contoh</button>
                </form>

                @forelse($previewLines as $line)
                    <div class="d-flex justify-content-between gap-2 py-1 border-bottom">
                        <span class="small">{{ $line['mapping']->feeComponent?->name }} <span class="text-muted">({{ ['fixed' => 'Tetap', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$line['mapping']->mode] ?? $line['mapping']->mode }})</span></span>
                        <span class="small fw-semibold">
                            @if($line['mapping']->mode === 'fixed')
                                {{ $line['amount'] === null ? 'Belum ada nominal' : 'Rp '.number_format($line['amount'], 0, ',', '.') }}
                            @elseif($line['mapping']->mode === 'pilihan')
                                {{ $line['options'] === [] ? 'Belum ada tingkat' : 'Pilih salah satu: '.collect($line['options'])->map(fn ($option) => 'Rp '.number_format($option, 0, ',', '.'))->implode(' / ') }}
                            @else
                                Diisi wali{{ $line['mapping']->min_amount ? ' (minimum Rp '.number_format($line['mapping']->min_amount, 0, ',', '.').')' : '' }}
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada komponen aktif pada unit ini.</p>
                @endforelse

                @if($previewLines !== [])
                    <div class="d-flex justify-content-between gap-2 pt-2">
                        <span class="fw-semibold">Total komponen Tetap</span>
                        <span class="fw-bold">Rp {{ number_format($previewFixedTotal, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Per-component cards: the minimum field follows the mode,
    // amount rows can be added/removed, and the footer button
    // saves header + body of that card only, via AJAX.
    document.querySelectorAll('.fee-card-form').forEach(function (form) {
        const modeSelect = form.querySelector('.mode-select');
        const minGroup = form.querySelector('.min-group');
        const editor = form.querySelector('.amounts-editor');
        const hint = form.querySelector('.unused-hint');
        const rowsBox = form.querySelector('.amounts-rows');

        function applyMode() {
            const mode = modeSelect.value;
            minGroup.classList.toggle('d-none', mode !== 'bebas');
            editor.classList.toggle('d-none', !(mode === 'fixed' || mode === 'pilihan'));
            hint.classList.toggle('d-none', mode !== 'unused');
        }

        modeSelect.addEventListener('change', applyMode);
        applyMode();

        let counter = form.querySelectorAll('.amount-row').length;
        form.querySelector('.row-add').addEventListener('click', function () {
            const template = document.getElementById('amount-row-template');
            const node = template.content.firstElementChild.cloneNode(true);
            node.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('__INDEX__', counter);
            });
            counter++;
            rowsBox.appendChild(node);
        });

        form.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.row-remove');
            if (removeButton) {
                removeButton.closest('.amount-row').remove();
            }
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const button = form.querySelector('.save-btn');
            const feedback = form.querySelector('.save-feedback');
            button.disabled = true;
            feedback.textContent = 'Menyimpan…';
            feedback.className = 'save-feedback small text-muted';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                    throw new Error(firstError || data.message || 'Gagal menyimpan kartu ini.');
                }

                feedback.textContent = data.message;
                feedback.className = 'save-feedback small text-success fw-semibold';
                form.querySelector('.nonaktif-badge').classList.toggle('d-none', data.active || modeSelect.value === 'unused');
                form.querySelector('.amounts-count').textContent = data.amounts_count;

                if (modeSelect.value === 'unused') {
                    rowsBox.innerHTML = '';
                }

                refreshPreview();
            } catch (error) {
                feedback.textContent = error.message;
                feedback.className = 'save-feedback small text-danger fw-semibold';
            } finally {
                button.disabled = false;
            }
        });
    });

    // Re-render the sample invoice from the server after a
    // card save, keeping the chosen preview combination.
    async function refreshPreview() {
        const card = document.getElementById('preview-card');
        if (!card) {
            return;
        }

        const keep = {};
        ['preview-track', 'preview-gender', 'preview-status'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                keep[id] = el.value;
            }
        });

        try {
            const html = await (await fetch(window.location.href, { headers: { 'Accept': 'text/html' } })).text();
            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('preview-card');
            if (!fresh) {
                return;
            }
            card.innerHTML = fresh.innerHTML;
            Object.entries(keep).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el) {
                    el.value = value;
                }
            });
        } catch (error) {
            // The preview simply stays as-is; the save itself succeeded.
        }
    }
</script>
@endpush
