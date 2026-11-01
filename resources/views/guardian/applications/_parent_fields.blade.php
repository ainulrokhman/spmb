{{--
    Identity section for one person (father / mother / guardian).
    Expects: $prefix ('father'|'mother'|'guardian'), $data (array),
    $withStatus (bool), $withRelation (bool, guardian only).
--}}
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-name">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error($prefix.'.name') is-invalid @enderror" id="{{ $prefix }}-name" name="{{ $prefix }}[name]" value="{{ old($prefix.'.name', $data['name'] ?? '') }}">
        @error($prefix.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if($withStatus)
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="{{ $prefix }}-status">Status <span class="text-danger">*</span></label>
            <select class="form-select @error($prefix.'.status') is-invalid @enderror" id="{{ $prefix }}-status" name="{{ $prefix }}[status]">
                <option value="">Pilih status...</option>
                @foreach(\App\Models\Application::PARENT_STATUSES as $status)
                    <option value="{{ $status }}" {{ old($prefix.'.status', $data['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            @error($prefix.'.status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif

    @if($withRelation ?? false)
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="{{ $prefix }}-relation">Hubungan dengan Anak <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error($prefix.'.relation_to_child') is-invalid @enderror" id="{{ $prefix }}-relation" name="{{ $prefix }}[relation_to_child]" value="{{ old($prefix.'.relation_to_child', $data['relation_to_child'] ?? '') }}" placeholder="mis. Paman, Kakek, Bibi">
            @error($prefix.'.relation_to_child')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-nik">NIK (opsional)</label>
        <input type="text" class="form-control @error($prefix.'.nik') is-invalid @enderror" id="{{ $prefix }}-nik" name="{{ $prefix }}[nik]" value="{{ old($prefix.'.nik', $data['nik'] ?? '') }}" placeholder="16 digit">
        @error($prefix.'.nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-phone">Nomor HP</label>
        <input type="text" class="form-control" id="{{ $prefix }}-phone" name="{{ $prefix }}[phone]" value="{{ old($prefix.'.phone', $data['phone'] ?? '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-job">Pekerjaan</label>
        <input type="text" class="form-control" id="{{ $prefix }}-job" name="{{ $prefix }}[job]" value="{{ old($prefix.'.job', $data['job'] ?? '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-education">Pendidikan Terakhir</label>
        <select class="form-select" id="{{ $prefix }}-education" name="{{ $prefix }}[education]">
            <option value="">Pilih...</option>
            @foreach(\App\Models\Application::EDUCATION_LEVELS as $level)
                <option value="{{ $level }}" {{ old($prefix.'.education', $data['education'] ?? '') === $level ? 'selected' : '' }}>{{ $level }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="{{ $prefix }}-income">Penghasilan Bulanan</label>
        <select class="form-select" id="{{ $prefix }}-income" name="{{ $prefix }}[income]">
            <option value="">Pilih...</option>
            @foreach(\App\Models\Application::INCOME_RANGES as $range)
                <option value="{{ $range }}" {{ old($prefix.'.income', $data['income'] ?? '') === $range ? 'selected' : '' }}>{{ $range }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold" for="{{ $prefix }}-address">Alamat (bila berbeda dengan anak)</label>
        <textarea class="form-control" id="{{ $prefix }}-address" name="{{ $prefix }}[address]" rows="2">{{ old($prefix.'.address', $data['address'] ?? '') }}</textarea>
    </div>
</div>
