            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Nominal Biaya</h5>
                    <p class="text-muted small mb-0">Leave the track and gender empty for a global amount. For pilihan fees, add several amounts at the same scope as tiers.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(session('error'))
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="fee_id" class="form-label fw-semibold text-secondary">Pemetaan Biaya (Unit — Komponen)</label>
                        <select class="form-select @error('fee_id') is-invalid @enderror" id="fee_id" name="fee_id" required>
                            <option value="">Pilih pemetaan...</option>
                            @foreach($fees as $fee)
                                <option value="{{ $fee->id }}" data-unit-id="{{ $fee->school_unit_id }}" {{ (string) old('fee_id', $feeAmount->fee_id ?? '') === (string) $fee->id ? 'selected' : '' }}>{{ $fee->schoolUnit?->name }} — {{ $fee->feeComponent?->name }} ({{ $fee->mode }})</option>
                            @endforeach
                        </select>
                        @error('fee_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($fees->isEmpty())
                            <div class="form-text text-danger small">Belum ada pemetaan biaya. Petakan komponen ke unit terlebih dahulu (Unit Sekolah → Biaya).</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="admission_track_id" class="form-label fw-semibold text-secondary">Jalur Pendaftaran</label>
                        <select class="form-select @error('admission_track_id') is-invalid @enderror" id="admission_track_id" name="admission_track_id">
                            <option value="">Semua jalur (global)</option>
                            @foreach($tracks as $track)
                                <option value="{{ $track->id }}" data-unit-id="{{ $track->school_unit_id }}" {{ (string) old('admission_track_id', $feeAmount->admission_track_id ?? '') === (string) $track->id ? 'selected' : '' }}>{{ $track->name }} — {{ $track->schoolUnit?->name }}</option>
                            @endforeach
                        </select>
                        @error('admission_track_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Hanya jalur milik unit dari pemetaan ini yang dapat dipakai; daftar tersaring otomatis setelah pemetaan dipilih.</div>
                    </div>

                    <div class="mb-3">
                        <label for="gender" class="form-label fw-semibold text-secondary">Jenis Kelamin</label>
                        <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                            <option value="">Semua jender</option>
                            @foreach(\App\Models\FeeAmount::GENDERS as $code => $label)
                                <option value="{{ $code }}" {{ old('gender', $feeAmount->gender ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('gender')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="amount" class="form-label fw-semibold text-secondary">Nominal (Rp)</label>
                            <input type="number" min="0" step="1" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $feeAmount->amount ?? '') }}" placeholder="e.g. 1500000" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sort_order" class="form-label fw-semibold text-secondary">Urutan</label>
                            <input type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $feeAmount->sort_order ?? 0) }}">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.fee-amounts.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ $submitLabel }} <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const feeSelect = document.getElementById('fee_id');
        const trackSelect = document.getElementById('admission_track_id');

        function filterTracks() {
            const unitId = feeSelect.selectedOptions[0]?.dataset.unitId || '';
            trackSelect.querySelectorAll('option[data-unit-id]').forEach(option => {
                option.hidden = unitId !== '' && option.dataset.unitId !== unitId;
            });
            if (trackSelect.selectedOptions[0]?.hidden) {
                trackSelect.value = '';
            }
        }

        feeSelect.addEventListener('change', filterTracks);
        filterTracks();
    });
</script>
@endpush
