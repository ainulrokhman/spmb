{{-- Shared printable registration form: child data, parent
     identities, dynamic answers and uploaded documents. --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="text-center border-bottom pb-3 mb-3">
            <h4 class="fw-bold mb-1">FORMULIR PENDAFTARAN SPMB</h4>
            <p class="text-muted mb-0">{{ $application->schoolUnit?->name }} &middot; Tahun Ajaran {{ $application->admissionWave?->academicYear?->name }}</p>
        </div>

        <table class="table table-sm">
            <tr><td class="text-muted" style="width: 220px;">Nomor Pendaftaran</td><td class="fw-semibold">{{ $application->registration_number }}</td></tr>
            <tr><td class="text-muted">Status</td><td>{{ $application->statusLabel() }}</td></tr>
            <tr><td class="text-muted">Nama Anak</td><td class="fw-semibold">{{ $application->child_name }}</td></tr>
            <tr><td class="text-muted">Tempat, Tanggal Lahir</td><td>{{ $application->birth_place }}, {{ $application->birth_date?->format('d M Y') }}</td></tr>
            <tr><td class="text-muted">Jenis Kelamin</td><td>{{ $application->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
            <tr><td class="text-muted">Unit</td><td>{{ $application->schoolUnit?->name }} ({{ $application->schoolUnit?->level }} &middot; {{ $application->schoolUnit?->program?->name }})</td></tr>
            <tr><td class="text-muted">Gelombang</td><td>{{ $application->admissionWave?->name }}</td></tr>
            <tr><td class="text-muted">Jalur</td><td>{{ $application->admissionTrack?->name }}</td></tr>
            @if($application->major)
                <tr><td class="text-muted">Jurusan</td><td>{{ $application->major->name }}</td></tr>
            @endif
            <tr><td class="text-muted">Wali Pendaftar</td><td>{{ $application->user?->name }} ({{ $application->user?->phone }}) — {{ \App\Models\Application::GUARDIAN_RELATIONS[$application->guardian_relation] ?? $application->guardian_relation }}</td></tr>
            <tr><td class="text-muted">Dikirim Pada</td><td>{{ $application->submitted_at?->format('d M Y H:i') }} WIB</td></tr>
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

        @if($formFields->isNotEmpty())
            <h6 class="fw-bold mt-4">Jawaban Formulir</h6>
            <table class="table table-sm mb-0">
                @foreach($formFields as $field)
                    @php($value = $application->answers[$field->key] ?? null)
                    <tr>
                        <td class="text-muted" style="width: 220px;">{{ $field->label }}</td>
                        <td>
                            @if($field->type === 'file' && is_string($value) && $value !== '')
                                Berkas terunggah ({{ basename($value) }})
                            @elseif(is_array($value))
                                {{ implode(', ', $value) }}
                            @else
                                {{ $value ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($application->documents->isNotEmpty())
            <h6 class="fw-bold mt-4">Berkas Terunggah</h6>
            <ul class="small mb-0">
                @foreach($application->documents as $doc)
                    <li>{{ $doc->documentType?->name }} — {{ $doc->original_name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
