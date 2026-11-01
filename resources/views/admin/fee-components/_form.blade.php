            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Komponen Biaya</h5>
                    <p class="text-muted small mb-0">Komponen bersifat bersama; setiap unit memetakan yang digunakannya dan mengatur nominalnya</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(session('error'))
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $feeComponent->name ?? '') }}" placeholder="e.g. Sumbangan Pengembangan Institusi" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Kode</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $feeComponent->code ?? '') }}" placeholder="e.g. SPI" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Kode singkat unik untuk komponen, disimpan dalam huruf kapital.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold text-secondary">Deskripsi</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2" placeholder="Deskripsi singkat (opsional)">{{ old('description', $feeComponent->description ?? '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $feeComponent->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.fee-components.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ $submitLabel }} <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
