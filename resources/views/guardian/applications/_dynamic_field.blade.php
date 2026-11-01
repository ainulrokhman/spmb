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
