@extends('layouts.admin')

@section('title', 'Syarat Berkas')
@section('page-title', 'Syarat Berkas: ' . $schoolUnit->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">School Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">Document Requirements</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-9 mx-auto">
        <form action="{{ route('admin.school-units.document-requirements.update', $schoolUnit->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Syarat Berkas — {{ $schoolUnit->name }}</h5>
                    <p class="text-muted small mb-0">{{ $schoolUnit->level }} &middot; {{ $schoolUnit->program }} &middot; Code {{ $schoolUnit->code }}. Choose for each document type whether this unit does not use it, accepts it optionally, or requires it from applicants.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if($documentTypes->isEmpty())
                        <p class="text-muted mb-0">No active document types exist yet. Create document types first.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-secondary fw-semibold">Jenis Berkas</th>
                                        <th class="text-secondary fw-semibold text-center">Tidak Dipakai</th>
                                        <th class="text-secondary fw-semibold text-center">Opsional</th>
                                        <th class="text-secondary fw-semibold text-center">Wajib</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documentTypes as $documentType)
                                        @php($current = $requirementMap[$documentType->id] ?? 'unused')
                                        <tr>
                                            <td>
                                                <span class="fw-semibold text-body d-block">{{ $documentType->name }}</span>
                                                <span class="text-muted small">{{ $documentType->code }} &middot; {{ $documentType->allowed_extensions }} &middot; max {{ number_format($documentType->max_size_kb) }} KB</span>
                                            </td>
                                            <td class="text-center">
                                                <input class="form-check-input" type="radio" name="requirements[{{ $documentType->id }}]" value="unused" {{ $current === 'unused' ? 'checked' : '' }} aria-label="Not used">
                                            </td>
                                            <td class="text-center">
                                                <input class="form-check-input" type="radio" name="requirements[{{ $documentType->id }}]" value="optional" {{ $current === 'optional' ? 'checked' : '' }} aria-label="Optional">
                                            </td>
                                            <td class="text-center">
                                                <input class="form-check-input" type="radio" name="requirements[{{ $documentType->id }}]" value="required" {{ $current === 'required' ? 'checked' : '' }} aria-label="Required">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary" {{ $documentTypes->isEmpty() ? 'disabled' : '' }}>
                    Simpan Syarat <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
