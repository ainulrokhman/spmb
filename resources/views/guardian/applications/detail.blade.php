@extends('layouts.guardian')

@section('title', 'Rincian Pendaftaran')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-bold mb-0">Rincian Pendaftaran</h3>
    <div class="d-flex gap-2">
        @if($application->registration_number)
            <a href="{{ route('guardian.applications.print', $application) }}" class="btn btn-outline-primary">
                <i class="bi bi-file-text"></i> Cetak Formulir
            </a>
        @endif
        <a href="{{ route('guardian.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dasbor Saya
        </a>
    </div>
</div>

@php($announced = $application->resultsAnnounced())
@php($resultHidden = in_array($application->status, ['passed', 'failed'], true) && ! $announced)

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Ringkasan</h5>
        <table class="table table-sm mb-0">
            <tr><td class="text-muted" style="width: 220px;">Nama Anak</td><td class="fw-semibold">{{ $application->child_name ?? '—' }}</td></tr>
            <tr><td class="text-muted">Nomor Pendaftaran</td><td class="fw-semibold">{{ $application->registration_number ?? 'Belum terbit (terbit setelah pendaftaran dikirim)' }}</td></tr>
            <tr><td class="text-muted">Status</td><td><span class="badge bg-primary">{{ $resultHidden ? 'Menunggu Pengumuman' : $application->statusLabel() }}</span></td></tr>
            <tr><td class="text-muted">Unit</td><td>{{ $application->schoolUnit?->name }} ({{ $application->schoolUnit?->level }} &middot; {{ $application->schoolUnit?->program?->name }})</td></tr>
            <tr><td class="text-muted">Gelombang</td><td>{{ $application->admissionWave?->name }} ({{ $application->admissionWave?->academicYear?->name }})</td></tr>
            <tr><td class="text-muted">Jalur</td><td>{{ $application->admissionTrack?->name }}</td></tr>
            @if($application->major)
                <tr><td class="text-muted">Jurusan</td><td>{{ $application->major->name }}</td></tr>
            @endif
            <tr><td class="text-muted">Status Anak</td><td>{{ $application->childStatus?->name ?? 'Reguler' }}</td></tr>
            <tr><td class="text-muted">Pendaftar</td><td>{{ $application->user?->name }} ({{ $application->user?->phone }}) — {{ \App\Models\Application::GUARDIAN_RELATIONS[$application->guardian_relation] ?? $application->guardian_relation }}</td></tr>
            @if($application->submitted_at)
                <tr><td class="text-muted">Dikirim Pada</td><td>{{ $application->submitted_at->format('d M Y H:i') }} WIB</td></tr>
            @endif
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-calendar-event text-primary"></i> Jadwal Penting</h5>
        <ul class="list-unstyled mb-0">
            @foreach($schedules as $item)
                <li class="d-flex justify-content-between gap-2 py-2 border-bottom">
                    <span>{{ $item['label'] }}@if($item['amount']) <span class="text-muted">· Rp {{ number_format($item['amount'], 0, ',', '.') }}</span>@endif</span>
                    <span class="text-end">
                        <strong>{{ $item['at'] ? $item['at']->format('d M Y H:i') : '—' }}</strong>
                        @if($item['note'])<span class="text-muted small d-block">{{ $item['note'] }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Biodata Anak</h5>
        <table class="table table-sm">
            <tr><td class="text-muted" style="width: 220px;">Nama Anak</td><td class="fw-semibold">{{ $application->child_name ?? '—' }}</td></tr>
            <tr><td class="text-muted">Tempat, Tanggal Lahir</td><td>{{ $application->birth_place ?? '—' }}, {{ $application->birth_date?->format('d M Y') ?? '—' }}</td></tr>
            <tr><td class="text-muted">Jenis Kelamin</td><td>{{ $application->gender === 'L' ? 'Laki-laki' : ($application->gender === 'P' ? 'Perempuan' : '—') }}</td></tr>
        </table>

        <h6 class="fw-bold mt-4">Identitas Orang Tua</h6>
        @foreach(['father_data' => 'Ayah', 'mother_data' => 'Ibu', 'guardian_data' => 'Wali'] as $key => $label)
            @if($application->{$key})
                <p class="fw-semibold mb-1 mt-3">{{ $label }}</p>
                <table class="table table-sm mb-0">
                    @foreach($application->{$key} as $field => $value)
                        @if($value !== null && $value !== '')
                            <tr>
                                <td class="text-muted" style="width: 220px;">{{ str_replace('_', ' ', ucfirst($field)) }}</td>
                                <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            @endif
        @endforeach
        @if(! $application->father_data && ! $application->mother_data && ! $application->guardian_data)
            <p class="text-muted small mb-0">Identitas orang tua belum diisi (diisi pada tahap formulir).</p>
        @endif
    </div>
</div>

@if($formFields->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Jawaban Formulir</h5>
            <table class="table table-sm mb-0">
                @foreach($formFields as $field)
                    @php($value = $application->answers[$field->key] ?? null)
                    <tr>
                        <td class="text-muted" style="width: 220px;">{{ $field->label }}</td>
                        <td>
                            @if($field->type === 'file' && is_string($value) && $value !== '')
                                Berkas terunggah ({{ basename($value) }})
                            @elseif($value === null || $value === '' || $value === [])
                                —
                            @else
                                {{ $field->answerDisplay($value) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Berkas Terunggah</h5>
        @if($application->documents->isNotEmpty())
            <ul class="list-unstyled mb-0">
                @foreach($application->documents as $doc)
                    <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                        <span>{{ $doc->documentType?->name }} — <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank" rel="noopener">{{ $doc->original_name }}</a></span>
                        <span class="badge bg-secondary">{{ $doc->verificationLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted small mb-0">Belum ada berkas yang diunggah.</p>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Pembayaran Formulir</h5>
        <table class="table table-sm">
            <tr>
                <td class="text-muted" style="width: 220px;">Biaya Formulir</td>
                <td>
                    @if($application->form_fee_amount === 0)
                        <strong class="text-success">Gratis</strong>
                    @else
                        Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }}
                        @if($application->discountAmount() > 0)
                            &middot; Diskon Rp {{ number_format($application->discountAmount(), 0, ',', '.') }}
                            &middot; Dibayar <strong>Rp {{ number_format($application->payableAmount(), 0, ',', '.') }}</strong>
                        @endif
                        &middot; <span class="badge bg-primary">{{ $application->feeStatusLabel() }}</span>
                    @endif
                </td>
            </tr>
        </table>

        @if($application->payments->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Tanggal</th>
                            <th class="text-secondary fw-semibold">Kanal</th>
                            <th class="text-secondary fw-semibold">Referensi</th>
                            <th class="text-secondary fw-semibold">Jumlah</th>
                            <th class="text-secondary fw-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($application->payments as $payment)
                            <tr>
                                <td class="small">{{ $payment->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $payment->channelLabel() }}</td>
                                <td class="small">{{ $payment->reference ?? '—' }}</td>
                                <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                <td>{{ $payment->statusLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted small mb-0">Belum ada pembayaran tercatat.</p>
        @endif

        @if($application->invoice)
            <a href="{{ route('guardian.invoices.show', $application) }}" class="btn btn-outline-primary btn-sm mt-3">
                <i class="bi bi-receipt"></i> Lihat Daftar Ulang &amp; Tagihan
            </a>
        @endif
    </div>
</div>
@endsection
