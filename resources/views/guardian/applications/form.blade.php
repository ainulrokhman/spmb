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
                    <label class="form-label fw-semibold" for="parent_name">Nama Orang Tua <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('parent_name') is-invalid @enderror" id="parent_name" name="parent_name" value="{{ old('parent_name', $application->parent_name) }}" required>
                    @error('parent_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="parent_phone">Nomor HP Orang Tua <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('parent_phone') is-invalid @enderror" id="parent_phone" name="parent_phone" value="{{ old('parent_phone', $application->parent_phone) }}" required>
                    @error('parent_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="guardian_relation">Hubungan Anda dengan Anak <span class="text-danger">*</span></label>
                    <select class="form-select @error('guardian_relation') is-invalid @enderror" id="guardian_relation" name="guardian_relation" required>
                        <option value="">Pilih hubungan...</option>
                        @foreach(\App\Models\Application::GUARDIAN_RELATIONS as $key => $label)
                            <option value="{{ $key }}" {{ old('guardian_relation', $application->guardian_relation) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('guardian_relation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Pilihan ini menentukan identitas mana yang terisi otomatis dari akun Anda.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Ayah</h5>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'father', 'data' => $father, 'withStatus' => true, 'withRelation' => false])
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Ibu</h5>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'mother', 'data' => $mother, 'withStatus' => true, 'withRelation' => false])
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold">Identitas Wali (bila ada)</h5>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="has_guardian" name="has_guardian" value="1" {{ old('has_guardian', $application->has_guardian) ? 'checked' : '' }}>
                <label class="form-check-label" for="has_guardian">Anak memiliki wali (selain ayah/ibu)</label>
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            @include('guardian.applications._parent_fields', ['prefix' => 'guardian', 'data' => $guardian, 'withStatus' => false, 'withRelation' => true])
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
@endsection
