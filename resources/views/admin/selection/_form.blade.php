@php
    $cfg = $rule->config ?? [];
    $lines = function (array $entries, string $format) {
        return collect($entries)->map(fn ($e) => $format === 'range'
            ? ($e['min'] ?? '').'|'.($e['max'] ?? '').'|'.($e['points'] ?? '')
            : $e)->implode("\n");
    };
    $bracketsText = old('brackets_text', isset($cfg['brackets']) ? collect($cfg['brackets'])->map(fn ($b) => ($b['min'] ?? '').'|'.($b['max'] ?? '').'|'.($b['points'] ?? ''))->implode("\n") : '');
    $rangesText = old('ranges_text', isset($cfg['ranges']) ? collect($cfg['ranges'])->map(fn ($r) => ($r['min'] ?? '').'|'.($r['max'] ?? '').'|'.($r['points'] ?? ''))->implode("\n") : '');
    $mapText = old('map_text', isset($cfg['map']) && ($rule->kind ?? '') === 'choice' ? collect($cfg['map'])->map(fn ($p, $a) => $a.'|'.$p)->implode("\n") : '');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="mb-3">
            <label for="name" class="form-label fw-semibold text-secondary">Nama Aturan <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $rule->name ?? '') }}" placeholder="cth. Umur Ideal Masuk" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="kind" class="form-label fw-semibold text-secondary">Jenis Aturan <span class="text-danger">*</span></label>
                <select class="form-select @error('kind') is-invalid @enderror" id="kind" name="kind" required>
                    <option value="">Pilih jenis...</option>
                    @foreach(\App\Models\SelectionRule::KIND_LABELS as $kind => $label)
                        <option value="{{ $kind }}" {{ old('kind', $rule->kind ?? '') === $kind ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3 mb-3">
                <label for="sort_order" class="form-label fw-semibold text-secondary">Urutan</label>
                <input type="number" min="0" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $rule->sort_order ?? 0) }}">
            </div>
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', ($rule->is_active ?? true) ? '1' : '') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
            </div>
        </div>

        {{-- Umur --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="age">
            <label for="brackets_text" class="form-label fw-semibold text-secondary">Rentang Umur</label>
            <textarea class="form-control font-monospace" id="brackets_text" name="brackets_text" rows="4" placeholder="5|6|10&#10;7||5">{{ $bracketsText }}</textarea>
            <div class="form-text">Satu rentang per baris: <code>umur_min|umur_maks|poin</code> (umur maks boleh kosong = tanpa batas atas). Umur dihitung per 1 Juli tahun ajaran pendaftar.</div>
        </div>

        {{-- Jawaban pilihan --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="choice">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Bidang Sumber</label>
                <select class="form-select" name="field_key">
                    <option value="">Pilih bidang...</option>
                    @foreach($choiceFields as $field)
                        <option value="{{ $field->key }}" {{ old('field_key', $cfg['field_key'] ?? '') === $field->key ? 'selected' : '' }}>{{ $field->label }} ({{ $field->key }})</option>
                    @endforeach
                </select>
                @if($choiceFields->isEmpty())<div class="form-text text-danger">Unit ini belum memiliki bidang bertipe select/radio/checkbox.</div>@endif
            </div>
            <label for="map_text" class="form-label fw-semibold text-secondary">Pemetaan Jawaban ke Poin</label>
            <textarea class="form-control font-monospace" id="map_text" name="map_text" rows="4" placeholder="Guru|10&#10;Wiraswasta|5">{{ $mapText }}</textarea>
            <div class="form-text">Satu jawaban per baris: <code>jawaban|poin</code>. Untuk checkbox, poin semua jawaban terpilih dijumlahkan.</div>
        </div>

        {{-- Rentang angka --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="numeric">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Bidang Sumber</label>
                <select class="form-select" name="field_key">
                    <option value="">Pilih bidang...</option>
                    @foreach($numberFields as $field)
                        <option value="{{ $field->key }}" {{ old('field_key', $cfg['field_key'] ?? '') === $field->key ? 'selected' : '' }}>{{ $field->label }} ({{ $field->key }})</option>
                    @endforeach
                </select>
                @if($numberFields->isEmpty())<div class="form-text text-danger">Unit ini belum memiliki bidang bertipe angka.</div>@endif
            </div>
            <label for="ranges_text" class="form-label fw-semibold text-secondary">Rentang Nilai</label>
            <textarea class="form-control font-monospace" id="ranges_text" name="ranges_text" rows="4" placeholder="80|100|10&#10;60|79|5">{{ $rangesText }}</textarea>
            <div class="form-text">Satu rentang per baris: <code>nilai_min|nilai_maks|poin</code> (nilai maks boleh kosong).</div>
        </div>

        {{-- Asal sekolah --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="origin_unit">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Bidang Sumber</label>
                <select class="form-select" name="field_key">
                    <option value="">Pilih bidang...</option>
                    @foreach($originFields as $field)
                        <option value="{{ $field->key }}" {{ old('field_key', $cfg['field_key'] ?? '') === $field->key ? 'selected' : '' }}>{{ $field->label }} ({{ $field->key }})</option>
                    @endforeach
                </select>
                @if($originFields->isEmpty())<div class="form-text text-danger">Unit ini belum memiliki bidang bertipe Pilihan Unit Sekolah.</div>@endif
            </div>
            <label for="points" class="form-label fw-semibold text-secondary">Poin bila lulusan unit yayasan</label>
            <input type="number" class="form-control" id="points" name="points" value="{{ old('points', $cfg['points'] ?? '') }}">
        </div>

        {{-- Saudara kandung --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="siblings">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Bidang Sumber</label>
                <select class="form-select" name="field_key">
                    <option value="">Pilih bidang...</option>
                    @foreach($siblingFields as $field)
                        <option value="{{ $field->key }}" {{ old('field_key', $cfg['field_key'] ?? '') === $field->key ? 'selected' : '' }}>{{ $field->label }} ({{ $field->key }})</option>
                    @endforeach
                </select>
                @if($siblingFields->isEmpty())<div class="form-text text-danger">Unit ini belum memiliki bidang bertipe Saudara Kandung.</div>@endif
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="points_internal" class="form-label fw-semibold text-secondary">Poin per saudara di unit yayasan</label>
                    <input type="number" class="form-control" id="points_internal" name="points_internal" value="{{ old('points_internal', $cfg['points_internal'] ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="points_external" class="form-label fw-semibold text-secondary">Poin per saudara di luar yayasan</label>
                    <input type="number" class="form-control" id="points_external" name="points_external" value="{{ old('points_external', $cfg['points_external'] ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="cap" class="form-label fw-semibold text-secondary">Batas maksimum (opsional)</label>
                    <input type="number" min="0" class="form-control" id="cap" name="cap" value="{{ old('cap', $cfg['cap'] ?? '') }}" placeholder="Tanpa batas">
                </div>
            </div>
        </div>

        {{-- Status anak --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="child_status">
            <label class="form-label fw-semibold text-secondary">Poin per Status Anak</label>
            <div class="row">
                @foreach($statuses as $status)
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text">{{ $status->name }}</span>
                            <input type="number" class="form-control" name="status_points[{{ $status->id }}]" value="{{ old('status_points.'.$status->id, ($rule->kind ?? '') === 'child_status' ? ($cfg['map'][$status->id] ?? '') : '') }}" placeholder="0">
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Kosongkan atau isi 0 untuk status tanpa poin. Status yang dipakai adalah status terverifikasi panitia.</div>
        </div>

        {{-- Kriteria manual --}}
        <div class="kind-block border rounded p-3 mb-3" data-kind="manual">
            <label for="max_points" class="form-label fw-semibold text-secondary">Poin Maksimum Kriteria</label>
            <input type="number" min="0" class="form-control" id="max_points" name="max_points" value="{{ old('max_points', $cfg['max_points'] ?? '') }}">
            <div class="form-text">Panitia mengisi poin kriteria ini per pendaftar (0 sampai maksimum) di halaman detail pendaftar, mis. Wawancara atau Tes Tahfidz.</div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.school-units.selection.index', $schoolUnit) }}" class="btn btn-light border">Kembali</a>
            <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Show only the config block of the chosen rule kind and
    // disable the hidden blocks' inputs so they never submit.
    document.addEventListener('DOMContentLoaded', function () {
        var kindSelect = document.getElementById('kind');
        var blocks = document.querySelectorAll('.kind-block');

        function refresh() {
            blocks.forEach(function (block) {
                var active = block.getAttribute('data-kind') === kindSelect.value;
                block.classList.toggle('d-none', !active);
                block.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !active;
                });
            });
        }

        kindSelect.addEventListener('change', refresh);
        refresh();
    });
</script>
@endpush
