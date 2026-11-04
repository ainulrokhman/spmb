@extends('layouts.admin')

@section('title', 'Detail Tagihan')
@section('page-title', 'Detail Tagihan Daftar Ulang')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index', array_filter(request()->only(['status']))) }}">Tagihan Daftar Ulang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
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
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

@php($application = $invoice->application)

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">{{ $application?->child_name }}</h5>
                <p class="text-muted small mb-0">
                    Wali: {{ $application?->user?->name }} ({{ $application?->user?->phone }}) &middot;
                    {{ $application?->schoolUnit?->name }} &middot; {{ $application?->admissionWave?->name }} &middot; {{ $application?->admissionTrack?->name }} &middot;
                    Nomor {{ $application?->registration_number }}
                </p>
            </div>
            <div class="card-body px-4 pb-4">
                <table class="table table-sm mb-0">
                    @foreach($invoice->items as $item)
                        <tr>
                            <td class="text-muted">{{ $item->component_name }} ({{ $item->component_code }}) — {{ \App\Models\Fee::MODES[$item->mode] ?? $item->mode }}</td>
                            <td class="text-end">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    @if($invoice->discountAmount() > 0)
                        <tr>
                            <td class="text-muted">Diskon ({{ \App\Models\Application::DISCOUNT_TYPES[$invoice->discount_type] ?? '' }} {{ $invoice->discount_type === 'percent' ? $invoice->discount_value.'%' : '' }})</td>
                            <td class="text-end text-success">− Rp {{ number_format($invoice->discountAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr class="fw-bold">
                        <td>Yang harus dibayar</td>
                        <td class="text-end">Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}</td>
                    </tr>
                    @if($invoice->paidAmount() > 0)
                        <tr>
                            <td class="text-muted">Sudah dibayar</td>
                            <td class="text-end text-success">Rp {{ number_format($invoice->paidAmount(), 0, ',', '.') }}</td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Sisa tagihan</td>
                            <td class="text-end">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Status Tagihan</td>
                        <td class="text-end"><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $invoice->statusLabel() }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Pendaftaran</td>
                        <td class="text-end">{{ $application?->statusLabel() }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Riwayat Pembayaran</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->payments->isEmpty() && $invoice->installments->every(fn ($i) => $i->payments->isEmpty()))
                    <p class="text-muted small mb-0">Belum ada pembayaran.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold">Referensi</th>
                                    <th class="text-secondary fw-semibold">Kanal</th>
                                    <th class="text-secondary fw-semibold text-end">Nominal</th>
                                    <th class="text-secondary fw-semibold">Status</th>
                                    <th class="text-secondary fw-semibold"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->reference }}</td>
                                        <td>{{ $payment->channel }}</td>
                                        <td class="text-end">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                        <td>{{ $payment->statusLabel() }}</td>
                                        <td class="text-end">
                                            @if($payment->channel === 'manual' && $payment->status === 'awaiting_confirmation')
                                                @if($payment->proof_path)
                                                    <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-receipt"></i> Bukti</a>
                                                @endif
                                                <form method="POST" action="{{ route('admin.invoices.confirm', $payment) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">Konfirmasi Lunas</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach($invoice->installments as $installment)
                                    @foreach($installment->payments as $payment)
                                        <tr>
                                            <td>{{ $payment->reference }} <span class="text-muted small">(Termin {{ $installment->sequence }})</span></td>
                                            <td>{{ $payment->channel }}</td>
                                            <td class="text-end">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                            <td>{{ $payment->statusLabel() }}</td>
                                            <td class="text-end">
                                                @if($payment->channel === 'manual' && $payment->status === 'awaiting_confirmation')
                                                    @if($payment->proof_path)
                                                        <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-receipt"></i> Bukti</a>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.invoices.confirm', $payment) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Konfirmasi Lunas</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Pengajuan Keringanan</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->request_status === 'pending')
                    @if($invoice->request_kind)
                        <p class="small mb-2">
                            <strong>{{ $invoice->request_kind === 'waiver' ? 'Pembebasan penuh' : 'Diskon' }}</strong>
                            @if($invoice->request_kind === 'discount')
                                — {{ \App\Models\Application::DISCOUNT_TYPES[$invoice->request_type] ?? '' }}
                                {{ $invoice->request_type === 'percent' ? $invoice->request_value.'%' : 'Rp '.number_format($invoice->request_value ?? 0, 0, ',', '.') }}
                            @endif
                        </p>
                    @endif
                    <p class="small text-muted">Alasan wali: {{ $invoice->request_reason }}</p>
                    @if($invoice->request_file_path)
                        <p class="small"><a href="{{ route('admin.invoices.relief-file', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-paperclip"></i> Lihat Lampiran Pengajuan</a></p>
                    @endif
                    <form method="POST" action="{{ route('admin.invoices.decide-discount', $invoice) }}">
                        @csrf
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="relief_type" class="form-label fw-semibold text-secondary">Bentuk Keringanan</label>
                                <select class="form-select" id="relief_type" name="relief_type">
                                    <option value="waiver">Bebaskan penuh</option>
                                    <option value="percent">Diskon persen</option>
                                    <option value="nominal">Diskon nominal</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="relief_value" class="form-label fw-semibold text-secondary">Nilai</label>
                                <input type="number" class="form-control" id="relief_value" name="relief_value" min="1" placeholder="% atau Rp">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                            <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="Wajib diisi bila meminta revisi"></textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Setujui</button>
                            <button type="submit" name="decision" value="revision" class="btn btn-warning" formnovalidate>Minta Revisi</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger" formnovalidate>Tolak</button>
                        </div>
                    </form>
                @elseif($invoice->discount_type !== null)
                    <p class="small mb-1">Keringanan sudah diberikan: <strong>{{ \App\Models\Application::DISCOUNT_TYPES[$invoice->discount_type] ?? '' }} {{ $invoice->discount_type === 'percent' ? $invoice->discount_value.'%' : 'Rp '.number_format($invoice->discount_value ?? 0, 0, ',', '.') }}</strong>@if($invoice->decider) oleh {{ $invoice->decider->name }}@endif.</p>
                    <p class="small text-muted mb-0">{{ $invoice->request_status === 'approved' ? 'Bermula dari pengajuan wali yang disetujui.' : 'Diberikan langsung oleh panitia tanpa pengajuan.' }}@if($invoice->decision_note) Catatan: {{ $invoice->decision_note }}@endif</p>
                @elseif($invoice->request_status)
                    <p class="small mb-1">Pengajuan terakhir: <strong>{{ $invoice->request_status === 'revision' ? 'Diminta Revisi — menunggu wali memperbaiki dan mengirim ulang' : 'Ditolak' }}</strong>@if($invoice->decider) oleh {{ $invoice->decider->name }}@endif.</p>
                    @if($invoice->decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->decision_note }}</p>
                    @endif
                @elseif($invoice->status !== 'paid' && $invoice->installments->isEmpty())
                    <p class="small text-muted">Tidak ada pengajuan. Berikan keringanan langsung:</p>
                    <form method="POST" action="{{ route('admin.invoices.grant-discount', $invoice) }}">
                        @csrf
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="grant_relief_type" class="form-label fw-semibold text-secondary">Bentuk Keringanan</label>
                                <select class="form-select" id="grant_relief_type" name="relief_type" required>
                                    <option value="waiver">Bebaskan penuh</option>
                                    <option value="percent">Diskon persen</option>
                                    <option value="nominal">Diskon nominal</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="grant_relief_value" class="form-label fw-semibold text-secondary">Nilai</label>
                                <input type="number" class="form-control" id="grant_relief_value" name="relief_value" min="1" placeholder="% atau Rp">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="grant_note" class="form-label fw-semibold text-secondary">Catatan</label>
                            <textarea class="form-control" id="grant_note" name="note" rows="2" maxlength="500" placeholder="Wajib diisi bila meminta revisi"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Berikan keringanan ini?');">Beri Keringanan</button>
                    </form>
                @else
                    <p class="text-muted small mb-0">Tidak ada pengajuan keringanan pada tagihan ini.</p>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Cicilan</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @php($shares = $invoice->componentPayableShares())
                @if($invoice->installment_request_status === 'pending')
                    <p class="small text-muted">Alasan wali: {{ $invoice->installment_request_reason }}</p>
                    <form method="POST" action="{{ route('admin.invoices.decide-installment', $invoice) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label fw-semibold text-secondary">Rincian Termin per Komponen</label>
                            <div class="form-text mb-2">Isi berapa bagian dari setiap komponen yang dibayar pada tiap termin. Total setiap kolom harus sama persis dengan yang harus dibayar untuk komponen itu (diskon tagihan sudah dibagi proporsional ke komponen). Total keseluruhan: <strong>Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}</strong>.</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle" data-matrix>
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-secondary fw-semibold">Jatuh Tempo</th>
                                            @foreach($invoice->items as $item)
                                                <th class="text-secondary fw-semibold">{{ $item->component_name }}<span class="d-block fw-normal small">harus Rp {{ number_format($shares[$item->id] ?? 0, 0, ',', '.') }}</span></th>
                                            @endforeach
                                            <th class="text-secondary fw-semibold">Total Termin</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="termin-rows">
                                        <tr class="termin-row">
                                            <td><input type="date" name="installments[0][due_date]" class="form-control form-control-sm" required></td>
                                            @foreach($invoice->items as $item)
                                                <td><input type="number" name="installments[0][items][{{ $item->id }}]" class="form-control form-control-sm matrix-cell" data-item="{{ $item->id }}" min="0" value="0"></td>
                                            @endforeach
                                            <td class="fw-semibold row-total">Rp 0</td>
                                            <td><button type="button" class="btn btn-outline-danger btn-sm remove-termin" title="Hapus termin">&times;</button></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="fw-semibold">Total per Komponen</td>
                                            @foreach($invoice->items as $item)
                                                <td class="fw-semibold col-total" data-item="{{ $item->id }}" data-target="{{ $shares[$item->id] ?? 0 }}">Rp 0</td>
                                            @endforeach
                                            <td class="fw-bold grand-total">Rp 0</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <button type="button" id="add-termin" class="btn btn-sm btn-outline-secondary">+ Tambah Termin</button>
                            <template id="termin-template">
                                <tr class="termin-row">
                                    <td><input type="date" name="installments[__INDEX__][due_date]" class="form-control form-control-sm" required></td>
                                    @foreach($invoice->items as $item)
                                        <td><input type="number" name="installments[__INDEX__][items][{{ $item->id }}]" class="form-control form-control-sm matrix-cell" data-item="{{ $item->id }}" min="0" value="0"></td>
                                    @endforeach
                                    <td class="fw-semibold row-total">Rp 0</td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-termin" title="Hapus termin">&times;</button></td>
                                </tr>
                            </template>
                        </div>
                        <div class="mb-3">
                            <label for="installment_note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                            <textarea class="form-control" id="installment_note" name="note" rows="2" maxlength="500" placeholder="Wajib diisi bila meminta revisi"></textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Setujui &amp; Buat Rincian</button>
                            <button type="submit" name="decision" value="revision" class="btn btn-warning" formnovalidate>Minta Revisi</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger" formnovalidate>Tolak</button>
                        </div>
                    </form>
                @elseif($invoice->installments->isNotEmpty())
                    <p class="small text-muted">{{ $invoice->installments->where('status', 'paid')->count() }} dari {{ $invoice->installments->count() }} termin lunas.@if($invoice->installmentDecider) Disetujui oleh {{ $invoice->installmentDecider->name }}.@endif Termin yang belum lunas dapat diubah dan termin baru dapat ditambah dalam satu kali simpan; termin lunas atau yang sedang menunggu pembayaran/konfirmasi terkunci. Total setiap kolom komponen harus tetap sama dengan yang harus dibayar untuk komponen itu. Urutan termin dihitung ulang mengikuti tanggal jatuh tempo.</p>
                    <form method="POST" action="{{ route('admin.invoices.revise-installments', $invoice) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" data-matrix>
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-secondary fw-semibold">Termin</th>
                                        <th class="text-secondary fw-semibold">Jatuh Tempo</th>
                                        @foreach($invoice->items as $item)
                                            <th class="text-secondary fw-semibold">{{ $item->component_name }}<span class="d-block fw-normal small">harus Rp {{ number_format($shares[$item->id] ?? 0, 0, ',', '.') }}</span></th>
                                        @endforeach
                                        <th class="text-secondary fw-semibold">Total</th>
                                        <th class="text-secondary fw-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->installments as $installment)
                                        @php($locked = $installment->status !== 'unpaid')
                                        @php($byItem = $installment->items->keyBy('invoice_item_id'))
                                        <tr>
                                            <td>{{ $installment->sequence }}</td>
                                            <td>
                                                @if($locked)
                                                    <span class="small">{{ $installment->due_date?->format('d/m/Y') }}</span>
                                                @else
                                                    <input type="date" name="rows[{{ $installment->id }}][due_date]" class="form-control form-control-sm" value="{{ $installment->due_date?->format('Y-m-d') }}" required>
                                                @endif
                                            </td>
                                            @foreach($invoice->items as $item)
                                                <td>
                                                    @if($locked)
                                                        <span class="small matrix-static" data-item="{{ $item->id }}" data-value="{{ $byItem->get($item->id)?->amount ?? 0 }}">Rp {{ number_format($byItem->get($item->id)?->amount ?? 0, 0, ',', '.') }}</span>
                                                    @else
                                                        <input type="number" name="rows[{{ $installment->id }}][items][{{ $item->id }}]" class="form-control form-control-sm matrix-cell" data-item="{{ $item->id }}" min="0" value="{{ $byItem->get($item->id)?->amount ?? 0 }}">
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="fw-semibold row-total">Rp {{ number_format($installment->amount, 0, ',', '.') }}</td>
                                            <td><span class="badge {{ $installment->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $installment->statusLabel() }}</span>@if($locked && $installment->status !== 'paid')<span class="d-block text-muted small">Terkunci</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tbody id="new-termin-rows"></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="fw-semibold">Total per Komponen</td>
                                        @foreach($invoice->items as $item)
                                            <td class="fw-semibold col-total" data-item="{{ $item->id }}" data-target="{{ $shares[$item->id] ?? 0 }}">Rp 0</td>
                                        @endforeach
                                        <td class="fw-bold grand-total">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @if($invoice->status !== 'paid')
                            <button type="button" id="add-revision-termin" class="btn btn-sm btn-outline-secondary">+ Tambah Termin</button>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Rincian</button>
                            <template id="new-termin-template">
                                <tr class="termin-row">
                                    <td><span class="text-muted small">Baru</span></td>
                                    <td><input type="date" name="new_terms[__INDEX__][due_date]" class="form-control form-control-sm" required></td>
                                    @foreach($invoice->items as $item)
                                        <td><input type="number" name="new_terms[__INDEX__][items][{{ $item->id }}]" class="form-control form-control-sm matrix-cell" data-item="{{ $item->id }}" min="0" value="0"></td>
                                    @endforeach
                                    <td class="fw-semibold row-total">Rp 0</td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-new-termin" title="Hapus termin">&times;</button></td>
                                </tr>
                            </template>
                        @endif
                    </form>
                @elseif($invoice->installment_request_status === 'revision')
                    <p class="small mb-1">Pengajuan cicilan terakhir: <strong>Diminta Revisi — menunggu wali memperbaiki dan mengirim ulang</strong>@if($invoice->installmentDecider) oleh {{ $invoice->installmentDecider->name }}@endif.</p>
                    @if($invoice->installment_decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->installment_decision_note }}</p>
                    @endif
                @elseif($invoice->installment_request_status === 'rejected')
                    <p class="small mb-1">Pengajuan cicilan terakhir: <strong>Ditolak</strong>@if($invoice->installmentDecider) oleh {{ $invoice->installmentDecider->name }}@endif.</p>
                    @if($invoice->installment_decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->installment_decision_note }}</p>
                    @endif
                @else
                    <p class="text-muted small mb-0">Tidak ada pengajuan cicilan pada tagihan ini. Rincian cicilan hanya dibuat dari pengajuan wali yang disetujui.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function formatRp(value) {
            return 'Rp ' + value.toLocaleString('id-ID');
        }

        // Live per-row and per-component totals for a matrix
        // table; a component column turns red until its total
        // matches the amount that must be paid for it.
        function recalc(table) {
            const totals = {};
            let grand = 0;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                let rowTotal = 0;

                row.querySelectorAll('.matrix-cell').forEach(function (input) {
                    const value = parseInt(input.value || '0', 10) || 0;
                    rowTotal += value;
                    totals[input.dataset.item] = (totals[input.dataset.item] || 0) + value;
                });
                row.querySelectorAll('.matrix-static').forEach(function (cell) {
                    const value = parseInt(cell.dataset.value || '0', 10) || 0;
                    rowTotal += value;
                    totals[cell.dataset.item] = (totals[cell.dataset.item] || 0) + value;
                });

                const rowTotalCell = row.querySelector('.row-total');
                if (rowTotalCell) {
                    rowTotalCell.textContent = formatRp(rowTotal);
                }
                grand += rowTotal;
            });

            table.querySelectorAll('.col-total').forEach(function (cell) {
                const sum = totals[cell.dataset.item] || 0;
                const target = parseInt(cell.dataset.target || '0', 10);
                cell.textContent = formatRp(sum);
                cell.classList.toggle('text-danger', sum !== target);
                cell.classList.toggle('text-success', sum === target);
            });

            const grandCell = table.querySelector('.grand-total');
            if (grandCell) {
                grandCell.textContent = formatRp(grand);
            }
        }

        document.querySelectorAll('[data-matrix]').forEach(function (table) {
            table.addEventListener('input', function () {
                recalc(table);
            });
            recalc(table);
        });

        function cloneTemplateRow(template, index) {
            const holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.split('__INDEX__').join(index);
            return holder.firstElementChild;
        }

        // Approval form: add/remove term rows.
        const rows = document.getElementById('termin-rows');
        const addBtn = document.getElementById('add-termin');
        const template = document.getElementById('termin-template');
        if (rows && addBtn && template) {
            let nextIndex = rows.children.length;
            addBtn.addEventListener('click', function () {
                rows.appendChild(cloneTemplateRow(template, nextIndex++));
                recalc(rows.closest('[data-matrix]'));
            });
            rows.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-termin');
                if (button && rows.children.length > 1) {
                    button.closest('.termin-row').remove();
                    recalc(rows.closest('[data-matrix]'));
                }
            });
        }

        // Revision form: add/remove NEW term rows.
        const newRows = document.getElementById('new-termin-rows');
        const addNewBtn = document.getElementById('add-revision-termin');
        const newTemplate = document.getElementById('new-termin-template');
        if (newRows && addNewBtn && newTemplate) {
            let nextNewIndex = 0;
            addNewBtn.addEventListener('click', function () {
                newRows.appendChild(cloneTemplateRow(newTemplate, nextNewIndex++));
                recalc(newRows.closest('[data-matrix]'));
            });
            newRows.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-new-termin');
                if (button) {
                    button.closest('.termin-row').remove();
                    recalc(newRows.closest('[data-matrix]'));
                }
            });
        }
    });
</script>
@endpush

@endsection
