            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Bidang Formulir</h5>
                    <p class="text-muted small mb-0">Core identity fields (name, birth data, guardian contact) are fixed and not defined here</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(session('error'))
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">Unit Sekolah</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Pilih unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $formField->school_unit_id ?? '') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program?->name }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="label" class="form-label fw-semibold text-secondary">Label</label>
                        <input type="text" class="form-control @error('label') is-invalid @enderror" id="label" name="label" value="{{ old('label', $formField->label ?? '') }}" placeholder="e.g. Pekerjaan Ayah" required>
                        @error('label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="key" class="form-label fw-semibold text-secondary">Kunci</label>
                        <input type="text" class="form-control @error('key') is-invalid @enderror" id="key" name="key" value="{{ old('key', $formField->key ?? '') }}" placeholder="e.g. pekerjaan_ayah" required>
                        @error('key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Nama mesin untuk jawaban yang disimpan: huruf kecil, angka, dan garis bawah, diawali huruf. Unik di dalam unit.</div>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label fw-semibold text-secondary">Tipe</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Pilih tipe...</option>
                            @foreach(\App\Models\FormField::TYPES as $type)
                                <option value="{{ $type }}" {{ old('type', $formField->type ?? '') === $type ? 'selected' : '' }}>{{ ['text' => 'Teks', 'textarea' => 'Teks Panjang', 'number' => 'Angka', 'date' => 'Tanggal', 'select' => 'Select (dropdown)', 'radio' => 'Radio', 'checkbox' => 'Checkbox', 'file' => 'Unggah File'][$type] ?? $type }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="options-group">
                        <label for="options_text" class="form-label fw-semibold text-secondary">Pilihan</label>
                        <textarea class="form-control @error('options_text') is-invalid @enderror" id="options_text" name="options_text" rows="4" placeholder="One option per line">{{ old('options_text', isset($formField) && $formField->options ? implode("\n", $formField->options) : '') }}</textarea>
                        @error('options_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Hanya untuk bidang select, radio, dan checkbox. Satu pilihan per baris.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="section" class="form-label fw-semibold text-secondary">Bagian</label>
                            <input type="text" class="form-control @error('section') is-invalid @enderror" id="section" name="section" value="{{ old('section', $formField->section ?? '') }}" placeholder="e.g. Data Siswa, Data Orang Tua">
                            @error('section')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sort_order" class="form-label fw-semibold text-secondary">Urutan</label>
                            <input type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $formField->sort_order ?? 0) }}">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="help_text" class="form-label fw-semibold text-secondary">Teks Bantuan</label>
                        <input type="text" class="form-control @error('help_text') is-invalid @enderror" id="help_text" name="help_text" value="{{ old('help_text', $formField->help_text ?? '') }}" placeholder="Optional hint shown under the field">
                        @error('help_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_required" id="is_required" value="1" {{ old('is_required', $formField->is_required ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_required">Wajib</label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $formField->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.form-fields.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ $submitLabel }} <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.getElementById('type');
        const optionsGroup = document.getElementById('options-group');
        const choiceTypes = ['select', 'radio', 'checkbox'];

        function refreshOptionsVisibility() {
            optionsGroup.classList.toggle('d-none', !choiceTypes.includes(typeSelect.value));
        }

        typeSelect.addEventListener('change', refreshOptionsVisibility);
        refreshOptionsVisibility();
    });
</script>
@endpush
