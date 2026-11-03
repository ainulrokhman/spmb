@extends('layouts.admin')

@section('title', 'Tetapkan dari Poin — Pratinjau')
@section('page-title', 'Tetapkan dari Poin')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.applications.index') }}" class="text-decoration-none">Verifikasi Pendaftaran</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pratinjau Poin</li>
@endsection

@section('content')
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="fw-bold mb-1">{{ $wave->schoolUnit?->name }} — {{ $wave->name }}</h5>
            <p class="text-muted small mb-0">
                Batas poin lulus: <strong>{{ $wave->passing_score }}</strong>.
                {{ $rows->where('decision', 'passed')->count() }} pendaftar akan Lulus dan {{ $rows->where('decision', 'failed')->count() }} Tidak Lulus
                dari {{ $rows->count() }} pendaftar Terverifikasi. Periksa daftar ini sebelum menerapkan — hasil baru terlihat oleh wali pada waktu pengumuman gelombang.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body px-4 py-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">#</th>
                            <th class="text-secondary fw-semibold">Nomor</th>
                            <th class="text-secondary fw-semibold">Anak</th>
                            <th class="text-secondary fw-semibold">Total Poin</th>
                            <th class="text-secondary fw-semibold">Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['application']->registration_number }}</td>
                                <td class="fw-semibold">{{ $row['application']->child_name }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>
                                    @if($row['decision'] === 'passed')
                                        <span class="badge bg-success">Lulus</span>
                                    @else
                                        <span class="badge bg-danger">Tidak Lulus</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pendaftar Terverifikasi pada gelombang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($rows->isNotEmpty())
        <form method="POST" action="{{ route('admin.selection.by-points.apply') }}" onsubmit="return confirm('Terapkan hasil ini ke {{ $rows->count() }} pendaftar?')">
            @csrf
            <input type="hidden" name="admission_wave_id" value="{{ $wave->id }}">
            <div class="d-flex justify-content-between">
                <a href="{{ route('admin.applications.index', ['admission_wave_id' => $wave->id]) }}" class="btn btn-light border">Kembali</a>
                <button type="submit" class="btn btn-primary">Terapkan Hasil</button>
            </div>
        </form>
    @endif
@endsection
