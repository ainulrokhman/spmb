{{--
    Renders one dynamic form field of the unit.
    Expects: $field (FormField), $value (current answer).
--}}
@php
    $key = 'answers.'.$field->key;
    $name = 'answers['.$field->key.']';
    $current = old($key, $value);
@endphp

<div class="mb-3">
    <label class="form-label fw-semibold" for="field-{{ $field->key }}">
        {{ $field->label }} @if($field->is_required)<span class="text-danger">*</span>@endif
    </label>

    @switch($field->type)
        @case('textarea')
            <textarea class="form-control @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" rows="3">{{ is_scalar($current) ? $current : '' }}</textarea>
            @break

        @case('select')
            <select class="form-select @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}">
                <option value="">Pilih...</option>
                @foreach($field->options ?? [] as $option)
                    <option value="{{ $option }}" {{ (string) $current === (string) $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            @foreach($field->options ?? [] as $option)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="{{ $name }}" id="field-{{ $field->key }}-{{ $loop->index }}" value="{{ $option }}" {{ (string) $current === (string) $option ? 'checked' : '' }}>
                    <label class="form-check-label" for="field-{{ $field->key }}-{{ $loop->index }}">{{ $option }}</label>
                </div>
            @endforeach
            @break

        @case('checkbox')
            @foreach($field->options ?? [] as $option)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="{{ $name }}[]" id="field-{{ $field->key }}-{{ $loop->index }}" value="{{ $option }}" {{ is_array($current) && in_array($option, $current) ? 'checked' : '' }}>
                    <label class="form-check-label" for="field-{{ $field->key }}-{{ $loop->index }}">{{ $option }}</label>
                </div>
            @endforeach
            @break

        @case('file')
            <input type="file" class="form-control @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf">
            @if(is_string($current) && $current !== '')
                <div class="form-text">Sudah terunggah: <a href="{{ asset('storage/'.$current) }}" target="_blank" rel="noopener">lihat berkas</a> (unggah lagi untuk mengganti)</div>
            @endif
            @break

        @case('date')
            <input type="date" class="form-control @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" value="{{ is_scalar($current) ? $current : '' }}">
            @break

        @case('number')
            <input type="number" class="form-control @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" value="{{ is_scalar($current) ? $current : '' }}">
            @break

        @case('school_unit')
            @php
                // The stored answer may be the structured shape
                // ({unit_id, school_name}), a legacy bare unit id,
                // or the legacy external marker; after a failed
                // submit, old input holds the raw select value and
                // the companion name input separately.
                $selectedSchool = '';
                $externalSchoolName = (string) old('school_unit_names.'.$field->key, '');
                if (is_array($current)) {
                    $selectedSchool = ! empty($current['unit_id']) ? (string) $current['unit_id'] : 'external';
                    if ($externalSchoolName === '') {
                        $externalSchoolName = (string) ($current['school_name'] ?? '');
                    }
                } elseif ($current !== null && $current !== '') {
                    $selectedSchool = (string) $current;
                }
            @endphp
            <select class="form-select @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" data-school-unit-select>
                <option value="">Pilih...</option>
                @foreach(\App\Models\SchoolUnit::where('is_active', true)->orderBy('name')->get() as $unitOption)
                    <option value="{{ $unitOption->id }}" {{ $selectedSchool === (string) $unitOption->id ? 'selected' : '' }}>{{ $unitOption->name }}</option>
                @endforeach
                <option value="external" {{ $selectedSchool === 'external' ? 'selected' : '' }}>Sekolah lain di luar yayasan</option>
            </select>
            <div class="mt-2" data-school-unit-external-name @if($selectedSchool !== 'external') style="display: none;" @endif>
                <label class="form-label" for="field-{{ $field->key }}-external-name">Nama sekolah luar <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('school_unit_names.'.$field->key) is-invalid @enderror" id="field-{{ $field->key }}-external-name" name="school_unit_names[{{ $field->key }}]" value="{{ $externalSchoolName }}" placeholder="Tulis nama sekolah di luar yayasan" maxlength="255">
                @error('school_unit_names.'.$field->key)
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            @break

        @case('siblings')
            @php
                $siblingRows = is_array($current) ? array_values($current) : [];
                if ($siblingRows === []) {
                    $siblingRows = [['name' => '', 'school' => '', 'school_name' => '']];
                }
                $siblingUnits = \App\Models\SchoolUnit::where('is_active', true)->orderBy('name')->get();
                $renderSiblingRow = function ($row, $index) use ($name, $siblingUnits) {
                    $school = $row['school'] ?? (! empty($row['unit_id']) ? (string) $row['unit_id'] : ((($row['school_name'] ?? '') !== '') ? 'external' : ''));
                    $html = '<div class="row g-2 mb-2" data-sibling-row>'
                        .'<div class="col-md-4"><input type="text" class="form-control" name="'.$name.'['.$index.'][name]" value="'.e($row['name'] ?? '').'" placeholder="Nama saudara"></div>'
                        .'<div class="col-md-4"><select class="form-select" name="'.$name.'['.$index.'][school]"><option value="">Pilih sekolah...</option>';
                    foreach ($siblingUnits as $unitOption) {
                        $html .= '<option value="'.$unitOption->id.'"'.((string) $school === (string) $unitOption->id ? ' selected' : '').'>'.e($unitOption->name).'</option>';
                    }
                    $html .= '<option value="external"'.($school === 'external' ? ' selected' : '').'>Sekolah lain di luar yayasan</option></select></div>'
                        .'<div class="col-md-3"><input type="text" class="form-control" name="'.$name.'['.$index.'][school_name]" value="'.e($row['school_name'] ?? '').'" placeholder="Nama sekolah luar (bila di luar yayasan)"></div>'
                        .'<div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" data-siblings-remove title="Hapus baris">&times;</button></div>'
                        .'</div>';

                    return $html;
                };
            @endphp
            <div data-siblings data-next-index="{{ count($siblingRows) }}">
                <div data-siblings-rows>
                    @foreach($siblingRows as $siblingIndex => $siblingRow)
                        {!! $renderSiblingRow($siblingRow, $siblingIndex) !!}
                    @endforeach
                </div>
                <template>{!! $renderSiblingRow(['name' => '', 'school' => '', 'school_name' => ''], '__INDEX__') !!}</template>
                <button type="button" class="btn btn-outline-primary btn-sm" data-siblings-add>+ Tambah Saudara</button>
                <div class="form-text">Satu baris per saudara kandung; kosongkan semua bila tidak ada saudara.</div>
                @foreach($errors->keys() as $errorKey)
                    @if(str_starts_with($errorKey, $key.'.'))
                        <div class="text-danger small">{{ $errors->first($errorKey) }}</div>
                    @endif
                @endforeach
            </div>
            @break

        @default
            <input type="text" class="form-control @error($key) is-invalid @enderror" id="field-{{ $field->key }}" name="{{ $name }}" value="{{ is_scalar($current) ? $current : '' }}">
    @endswitch

    @if($field->help_text)
        <div class="form-text">{{ $field->help_text }}</div>
    @endif
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
