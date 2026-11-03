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
        <form action="{{ route('admin.school-units.fees.update', $schoolUnit->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Pemetaan Biaya — {{ $schoolUnit->name }}</h5>
                    <p class="text-muted small mb-0">{{ $schoolUnit->level }} &middot; {{ $schoolUnit->program?->name }}</p>
                    <div class="small mt-2">
                        <span class="fw-semibold">Cara kerja halaman ini:</span>
                        <ol class="mb-2 ps-3">
                            <li>Untuk setiap komponen: nyalakan <strong>Aktif</strong> dan pilih <strong>Mode</strong>-nya, lalu klik <strong>Simpan Pemetaan</strong>.</li>
                            <li>Isi nominal/tingkat komponen pada kartu <strong>Nominal &amp; Tingkat</strong> di bawah (atau lewat menu Nominal Biaya).</li>
                            <li>Periksa hasilnya pada kartu <strong>Contoh Tagihan</strong> paling bawah sebelum membuka pendaftaran.</li>
                        </ol>
                        <span class="text-muted">Arti mode bagi wali — <strong>Tetap</strong>: wali membayar nominal yang ditentukan sekolah. <strong>Pilihan</strong>: wali memilih salah satu tingkat yang disediakan. <strong>Bebas</strong>: wali mengisi nominal sendiri (dapat diberi minimum). <strong>Tidak Dipakai</strong>: komponen dihapus dari unit ini beserta nominalnya. Menonaktifkan komponen (saklar Aktif) berbeda: nominal tetap tersimpan, tetapi komponen tidak ditagihkan selama nonaktif.</span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    @if($errors->any())
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    @forelse($components as $component)
                        @php($mapping = $mappings->get($component->id))
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3 border-bottom">
                            <div>
                                <span class="fw-semibold text-body d-block">{{ $component->name }} @if($mapping && ! $mapping->is_active)<span class="badge bg-secondary">Nonaktif</span>@endif</span>
                                <span class="text-muted small d-block">{{ $component->code }}@if($mapping) &middot; {{ $mapping->amounts_count }} nominal/tingkat @endif</span>
                                <span class="text-muted small d-block">
                                    @if(! $mapping)
                                        Belum dipakai di unit ini — pilih mode untuk mulai memakainya.
                                    @elseif(! $mapping->is_active)
                                        Sedang nonaktif: tidak ditagihkan ke wali, nominal tetap tersimpan.
                                    @elseif($mapping->mode === 'fixed')
                                        Wali membayar nominal yang ditentukan sekolah.
                                    @elseif($mapping->mode === 'pilihan')
                                        Wali memilih salah satu tingkat nominal yang disediakan.
                                    @else
                                        Wali mengisi nominal sendiri (boleh diberi minimum).
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex align-items-end gap-2 flex-wrap">
                                <div class="form-check form-switch pb-2">
                                    <input type="hidden" name="mappings[{{ $component->id }}][is_active]" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="aktif-{{ $component->id }}"
                                           name="mappings[{{ $component->id }}][is_active]" value="1"
                                           {{ old('mappings.'.$component->id.'.is_active', $mapping?->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="aktif-{{ $component->id }}">Aktif</label>
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold mb-1" for="min-{{ $component->id }}">Minimum (Bebas)</label>
                                    <input type="number" min="0" step="1" id="min-{{ $component->id }}"
                                           name="mappings[{{ $component->id }}][min_amount]"
                                           value="{{ old('mappings.'.$component->id.'.min_amount', $mapping?->min_amount) }}"
                                           class="form-control" style="width: 170px;"
                                           placeholder="Rp"
                                           aria-label="Jumlah minimum untuk {{ $component->name }}">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold mb-1" for="mode-{{ $component->id }}">Mode</label>
                                    <select name="mappings[{{ $component->id }}][mode]" id="mode-{{ $component->id }}" class="form-select" style="width: 190px;" aria-label="Mode untuk {{ $component->name }}">
                                        <option value="unused" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === 'unused' ? 'selected' : '' }}>Tidak Dipakai</option>
                                        @foreach(\App\Models\Fee::MODES as $mode)
                                            <option value="{{ $mode }}" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === $mode ? 'selected' : '' }}>{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mode] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($mapping)
                                    <a href="{{ route('admin.fee-amounts.index', ['school_unit_id' => $schoolUnit->id, 'fee_component_id' => $component->id]) }}" class="btn btn-light border py-2 px-3 fw-medium">
                                        <i class="bi bi-cash-stack text-success"></i> Nominal
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No active fee components exist yet. Create components under Fee Components first.</p>
                    @endforelse
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan Pemetaan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-4 mt-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Nominal &amp; Tingkat — {{ $schoolUnit->name }}</h5>
                <p class="text-muted small mb-0">Nominal setiap komponen pada unit ini. Aturan paling spesifik yang menang: jalur + jender, lalu jalur, lalu jender, lalu global (kosongkan jalur dan jender). Untuk mode pilihan, tambahkan beberapa nominal sebagai tingkat.</p>
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

                @forelse($components as $component)
                    @php($mapping = $mappings->get($component->id))
                    @if($mapping)
                        <div class="py-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold text-body">{{ $component->name }}</span>
                                <span class="badge bg-secondary">{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mapping->mode] ?? $mapping->mode }}</span>
                                @if(! $mapping->is_active)
                                    <span class="badge bg-dark">Nonaktif — tidak ditagihkan</span>
                                @endif
                                @if(in_array($mapping->mode, ['fixed', 'pilihan'], true) && $mapping->amounts_count === 0)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Belum ada nominal — komponen ini belum dapat ditagihkan</span>
                                @endif
                            </div>

                            @foreach($mapping->amounts as $amount)
                                <div class="d-flex align-items-center justify-content-between gap-2 py-1">
                                    <span class="small">
                                        <span class="fw-semibold">Rp {{ number_format($amount->amount, 0, ',', '.') }}</span>
                                        <span class="text-muted">&middot; {{ $amount->admissionTrack?->name ?? 'Semua Jalur' }} &middot; {{ $amount->gender ? (\App\Models\FeeAmount::GENDERS[$amount->gender] ?? $amount->gender) : 'Semua Jender' }} &middot; {{ $amount->childStatus?->name ?? 'Semua Status' }} &middot; urutan {{ $amount->sort_order }}</span>
                                    </span>
                                    <span class="d-flex gap-1">
                                        @can('fee-amount-edit')
                                            <a href="{{ route('admin.fee-amounts.edit', $amount->id) }}?return_unit_id={{ $schoolUnit->id }}" class="btn btn-sm btn-light border" title="Ubah Nominal"><i class="bi bi-pencil-square text-primary"></i></a>
                                        @endcan
                                        @can('fee-amount-delete')
                                            <form action="{{ route('admin.fee-amounts.destroy', $amount->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus nominal ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_unit_id" value="{{ $schoolUnit->id }}">
                                                <button type="submit" class="btn btn-sm btn-light border" title="Hapus Nominal"><i class="bi bi-trash text-danger"></i></button>
                                            </form>
                                        @endcan
                                    </span>
                                </div>
                            @endforeach

                            @can('fee-amount-create')
                                <form action="{{ route('admin.fee-amounts.store') }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                    @csrf
                                    <input type="hidden" name="fee_id" value="{{ $mapping->id }}">
                                    <input type="hidden" name="return_unit_id" value="{{ $schoolUnit->id }}">
                                    <input type="number" name="amount" class="form-control form-control-sm" style="width: 160px;" placeholder="Nominal (Rp)" min="0" step="1" required aria-label="Nominal baru untuk {{ $component->name }}">
                                    <select name="admission_track_id" class="form-select form-select-sm" style="width: 170px;" aria-label="Jalur untuk nominal baru">
                                        <option value="">Semua Jalur</option>
                                        @foreach($tracks as $track)
                                            <option value="{{ $track->id }}">{{ $track->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="gender" class="form-select form-select-sm" style="width: 150px;" aria-label="Jender untuk nominal baru">
                                        <option value="">Semua Jender</option>
                                        @foreach(\App\Models\FeeAmount::GENDERS as $genderCode => $genderLabel)
                                            <option value="{{ $genderCode }}">{{ $genderLabel }}</option>
                                        @endforeach
                                    </select>
                                    <select name="child_status_id" class="form-select form-select-sm" style="width: 160px;" aria-label="Status anak untuk nominal baru">
                                        <option value="">Semua Status</option>
                                        @foreach($childStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="sort_order" class="form-control form-control-sm" style="width: 90px;" placeholder="Urutan" value="0" aria-label="Urutan nominal baru">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                @empty
                    <p class="text-muted mb-0">Belum ada komponen biaya aktif.</p>
                @endforelse

                @if($mappings->isEmpty())
                    <p class="text-muted mb-0">Belum ada pemetaan biaya pada unit ini. Atur mode komponen pada kartu Pemetaan Biaya di atas terlebih dahulu, lalu nominal dapat ditambahkan di sini.</p>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
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
                        <label class="form-label small fw-semibold mb-1" for="preview-gender">Jender</label>
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
