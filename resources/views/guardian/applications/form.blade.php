@extends('layouts.guardian')

@section('title', 'Isi Formulir')

@section('content')
<h3 class="fw-bold mb-1">Tahap 2 — Isi Formulir</h3>
<p class="text-muted mb-4">{{ $application->schoolUnit?->name }} &middot; {{ $application->admissionWave?->name }} &middot; {{ $application->admissionTrack?->name }}</p>

<form method="POST" action="{{ route('guardian.applications.form.save', $application) }}" enctype="multipart/form-data">
    @csrf

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Data Anak</h5>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="child_name">Nama Lengkap Anak <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('child_name') is-invalid @enderror" id="child_name" name="child_name" value="{{ old('child_name', $application->child_name) }}" required>
                    @error('child_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="gender">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                        <option value="">Pilih...</option>
                        <option value="L" {{ old('gender', $application->gender) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender', $application->gender) === 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="child_status_id">Status Anak</label>
                    <select class="form-select @error('child_status_id') is-invalid @enderror" id="child_status_id" name="child_status_id">
                        <option value="">Reguler (umum)</option>
                        @foreach($childStatuses as $status)
                            @if($status->name !== 'Reguler')
                                <option value="{{ $status->id }}" {{ (int) old('child_status_id', $application->child_status_id) === $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <div class="form-text">Pilih bila sesuai; status akan diverifikasi panitia bersama berkas pendukung.</div>
                    @error('child_status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="birth_place">Tempat Lahir <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('birth_place') is-invalid @enderror" id="birth_place" name="birth_place" value="{{ old('birth_place', $application->birth_place) }}" required>
                    @error('birth_place')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="birth_date">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" value="{{ old('birth_date', $application->birth_date?->format('Y-m-d')) }}" required>
                    @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="guardian_relation_info">Hubungan Anda dengan Anak</label>
                    <input type="text" class="form-control" id="guardian_relation_info" value="{{ \App\Models\Application::GUARDIAN_RELATIONS[$application->guardian_relation] ?? '—' }}" disabled>
                    <div class="form-text">Dipilih saat mulai pendaftaran; identitas yang sesuai terisi otomatis dari akun Anda. Data ayah/ibu/wali diisi pada kartu identitas di bawah.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Ayah</h5>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'father', 'data' => $father, 'withStatus' => true, 'withRelation' => false, 'withAccount' => $application->guardian_relation !== 'ayah', 'accountLabel' => 'ayah'])
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Ibu</h5>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'mother', 'data' => $mother, 'withStatus' => true, 'withRelation' => false, 'withAccount' => $application->guardian_relation !== 'ibu', 'accountLabel' => 'ibu'])
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Wali (bila ada)</h5>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="has_guardian" name="has_guardian" value="1" {{ old('has_guardian', $hasGuardianDefault ?? $application->has_guardian) ? 'checked' : '' }}>
                <label class="form-check-label" for="has_guardian">Anak memiliki wali (selain ayah/ibu)</label>
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'guardian', 'data' => $guardian, 'withStatus' => false, 'withRelation' => true, 'withAccount' => $application->guardian_relation !== 'wali', 'accountLabel' => 'wali'])
            <p class="form-text mb-0">Isi bagian ini hanya bila kotak di atas dicentang.</p>
        </div>
    </div>

    @foreach($fields as $section => $sectionFields)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold">{{ $section }}</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @foreach($sectionFields as $field)
                    @include('guardian.applications._dynamic_field', ['field' => $field, 'value' => $application->answers[$field->key] ?? null])
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="d-flex justify-content-end gap-2 mb-5">
        <button type="submit" class="btn btn-primary">Simpan &amp; Lanjut ke Berkas</button>
    </div>
</form>

@push('scripts')
<script>
    // Sibling repeater: add clones the template row with the
    // next index; remove drops a row. Indices need not be
    // sequential — the server re-indexes the kept rows.
    document.querySelectorAll('[data-siblings]').forEach(function (box) {
        var rows = box.querySelector('[data-siblings-rows]');
        var template = box.querySelector('template');
        var nextIndex = parseInt(box.getAttribute('data-next-index') || '1', 10);

        box.querySelector('[data-siblings-add]').addEventListener('click', function () {
            var holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.split('__INDEX__').join(nextIndex++);
            rows.appendChild(holder.firstElementChild);
        });

        rows.addEventListener('click', function (event) {
            var button = event.target.closest('[data-siblings-remove]');
            if (button) {
                button.closest('[data-sibling-row]').remove();
            }
        });
    });
</script>
@endpush
@endsection
