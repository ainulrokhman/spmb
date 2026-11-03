@extends('layouts.admin')

@section('title', 'Seleksi Poin — ' . $schoolUnit->name)
@section('page-title', 'Seleksi Poin')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">Unit Sekolah</a></li>
    <li class="breadcrumb-item active" aria-current="page">Seleksi — {{ $schoolUnit->name }}</li>
@endsection

@section('content')
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-1">{{ $schoolUnit->name }}</h5>
                <p class="text-muted small mb-0">
                    Seleksi poin bersifat opsional per unit. Bila aktif, poin dihitung otomatis saat pendaftar Terverifikasi;
                    total = poin otomatis + kriteria manual + koreksi panitia. Wali tidak melihat poin.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.school-units.selection.toggle', $schoolUnit) }}" class="d-flex align-items-center gap-2">
                @csrf
                @method('PATCH')
                @if($schoolUnit->selection_enabled)
                    <span class="badge bg-success">Seleksi Aktif</span>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Nonaktifkan</button>
                @else
                    <span class="badge bg-secondary">Seleksi Nonaktif</span>
                    <button type="submit" class="btn btn-primary btn-sm">Aktifkan Seleksi</button>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Aturan Poin</h5>
            <a href="{{ route('admin.school-units.selection.create', $schoolUnit) }}" class="btn btn-primary btn-sm">+ Tambah Aturan</a>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Urutan</th>
                            <th class="text-secondary fw-semibold">Nama Aturan</th>
                            <th class="text-secondary fw-semibold">Jenis</th>
                            <th class="text-secondary fw-semibold">Pengaturan</th>
                            <th class="text-secondary fw-semibold">Status</th>
                            <th class="text-secondary fw-semibold text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td>{{ $rule->sort_order }}</td>
                                <td class="fw-semibold">{{ $rule->name }}</td>
                                <td><span class="badge bg-info">{{ \App\Models\SelectionRule::KIND_LABELS[$rule->kind] ?? $rule->kind }}</span></td>
                                <td class="small text-muted">{{ $rule->summary() }}</td>
                                <td>
                                    @if($rule->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.school-units.selection.edit', [$schoolUnit, $rule]) }}" class="btn btn-sm btn-light border">Ubah</a>
                                    <form method="POST" action="{{ route('admin.school-units.selection.destroy', [$schoolUnit, $rule]) }}" class="d-inline" onsubmit="return confirm('Hapus aturan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border text-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada aturan. Tambahkan aturan pertama untuk mulai menghitung poin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
