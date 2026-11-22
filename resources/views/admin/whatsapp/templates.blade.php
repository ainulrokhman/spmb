@extends('layouts.admin')

@section('title', 'Template Pesan WhatsApp')
@section('page-title', 'Template Pesan WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
    <li class="breadcrumb-item active" aria-current="page">Template Pesan</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body"><i class="bi bi-chat-text me-2"></i>Template per Jenis Pesan</h5>
        <p class="text-muted small mb-0">Setiap jenis pesan otomatis mempunyai satu template. Template yang dinonaktifkan tidak akan dikirim sama sekali. Variabel seperti <code>{nama_anak}</code> diganti otomatis saat pesan dikirim.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Jenis Pesan</th>
                        <th>Status</th>
                        <th>Cuplikan Isi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        <tr>
                            <td class="fw-semibold">{{ $template->typeLabel() }}</td>
                            <td>
                                @if($template->is_active)
                                    <span class="badge text-bg-success">Aktif</span>
                                @else
                                    <span class="badge text-bg-secondary">Nonaktif</span>
                                @endif
                                @if(!$template->exists)
                                    <span class="badge text-bg-info">Bawaan</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ \Illuminate\Support\Str::limit($template->preview(), 90) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.whatsapp.templates.edit', $template->type) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Ubah
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
