@extends('layouts.guardian')

@section('title', 'Dasbor Saya')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Pendaftaran Anak Saya</h3>
        <p class="text-muted mb-0">Untuk setiap anak terlihat apa yang sudah selesai, apa yang sedang berjalan, dan apa yang masih perlu dilakukan.</p>
    </div>
    <a href="{{ route('guardian.applications.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Daftarkan Anak
    </a>
</div>

@if($applications->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <p class="text-muted mt-3">Belum ada pendaftaran. Mulai dengan menekan tombol "Daftarkan Anak".</p>
            <a href="{{ route('admissions.info') }}" class="btn btn-outline-primary">Lihat Unit yang Membuka Pendaftaran</a>
        </div>
    </div>
@else
    @if(! empty($actions))
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell-fill text-primary"></i> Perlu Tindakan Anda</span>
                <span class="badge bg-danger">{{ count($actions) }}</span>
            </div>
            <div class="list-group list-group-flush">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] }}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center gap-2">
                        <span>
                            <span class="fw-semibold">{{ $action['label'] }}</span>
                            <span class="text-muted">&mdash; {{ $action['application']->child_name }}</span>
                            @if($action['detail'])
                                <span class="d-block small {{ $action['attention'] ? 'text-danger' : 'text-muted' }}">{{ $action['detail'] }}</span>
                            @endif
                        </span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @php($stepIcons = [
        'done' => ['bi-check-circle-fill', 'text-success'],
        'current' => ['bi-arrow-right-circle-fill', 'text-primary'],
        'attention' => ['bi-exclamation-circle-fill', 'text-danger'],
        'waiting' => ['bi-hourglass-split', 'text-warning'],
        'todo' => ['bi-circle', 'text-muted'],
        'stopped' => ['bi-x-circle-fill', 'text-danger'],
    ])

    <div class="row g-3">
        @foreach($applications as $application)
            @php($announced = $application->resultsAnnounced())
            @php($resultHidden = in_array($application->status, ['passed', 'failed'], true) && ! $announced)
            @php($applicationSteps = $steps[$application->id] ?? [])
            @php($primaryStep = collect($applicationSteps)->first(fn ($step) => in_array($step['state'], ['current', 'attention'], true)))
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <h5 class="fw-bold mb-1">{{ $application->child_name ?? 'Pendaftaran Baru' }}</h5>
                            <span class="badge bg-primary text-wrap text-start">{{ $resultHidden ? 'Menunggu Pengumuman' : $application->statusLabel() }}</span>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ $application->schoolUnit?->name }} &middot; {{ $application->admissionWave?->name }} ({{ $application->admissionWave?->academicYear?->name }}) &middot; {{ $application->admissionTrack?->name }}
                        </p>
                        @if($application->registration_number)
                            <p class="small mb-2">Nomor Pendaftaran: <strong>{{ $application->registration_number }}</strong></p>
                        @endif
                        <p class="small mb-1">
                            Biaya Formulir:
                            @if($application->form_fee_amount === 0)
                                <strong class="text-success">Gratis</strong>
                            @else
                                <strong>Rp {{ number_format($application->payableAmount(), 0, ',', '.') }}</strong>
                                <span class="text-muted">({{ $application->feeStatusLabel() }})</span>
                            @endif
                        </p>
                        @if($application->invoice)
                            <p class="small mb-2">
                                Tagihan Daftar Ulang: <strong>Rp {{ number_format($application->invoice->total, 0, ',', '.') }}</strong>
                                <span class="text-muted">({{ $application->invoice->statusLabel() }})</span>
                                @if($application->invoice->remainingAmount() > 0)
                                    &middot; Sisa <strong>Rp {{ number_format($application->invoice->remainingAmount(), 0, ',', '.') }}</strong>
                                @endif
                            </p>
                        @endif

                        <div class="d-flex flex-column gap-2 my-3">
                            @foreach($applicationSteps as $step)
                                @php($icon = $stepIcons[$step['state']] ?? $stepIcons['todo'])
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi {{ $icon[0] }} {{ $icon[1] }} fs-5 lh-1"></i>
                                    <div>
                                        <span class="{{ in_array($step['state'], ['current', 'attention'], true) ? 'fw-semibold' : '' }}">{{ $step['label'] }}</span>
                                        @if($step['state'] === 'waiting')
                                            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Menunggu Panitia</span>
                                        @endif
                                        @if($step['note'])
                                            <span class="d-block small {{ $step['state'] === 'attention' ? 'text-danger' : 'text-muted' }}">{{ $step['note'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @php($nextSchedule = $application->nextSchedule())
                        @if($nextSchedule)
                            <p class="small mb-3"><i class="bi bi-calendar-event text-primary"></i> Jadwal terdekat: <strong>{{ $nextSchedule['label'] }}</strong> — {{ $nextSchedule['at']->format('d M Y H:i') }}</p>
                        @endif

                        <div class="mt-auto">
                            @if($primaryStep)
                                <a href="{{ $primaryStep['action_url'] }}" class="btn btn-primary w-100 py-2">{{ $primaryStep['action_label'] }}</a>
                            @elseif($application->status === 're_registered')
                                <a href="{{ route('guardian.invoices.show', $application) }}" class="btn btn-outline-primary w-100 py-2">Lihat Daftar Ulang</a>
                            @elseif($application->invoice && $application->status === 'passed' && $announced)
                                <a href="{{ route('guardian.invoices.show', $application) }}" class="btn btn-outline-primary w-100 py-2">Lihat Tagihan</a>
                            @endif
                            <a href="{{ route('guardian.applications.detail', $application) }}" class="btn btn-outline-secondary w-100 py-2 mt-2">Lihat Rincian</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
