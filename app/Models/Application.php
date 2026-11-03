<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * Flow statuses in strict step order (Bab 3 of the design doc):
     * the form fee is settled before the form opens, documents come
     * after the form, and verification belongs to Fase 3.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'awaiting_form_fee' => 'Menunggu Pembayaran Formulir',
        'filling_form' => 'Mengisi Formulir',
        'uploading_documents' => 'Mengunggah Berkas',
        'submitted' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'needs_revision' => 'Perlu Perbaikan',
        'rejected' => 'Ditolak',
        'passed' => 'Lulus',
        'failed' => 'Tidak Lulus',
        're_registered' => 'Daftar Ulang',
    ];

    /**
     * Form fee payment states.
     *
     * @var array<string, string>
     */
    public const FEE_STATUSES = [
        'free' => 'Gratis',
        'unpaid' => 'Belum Bayar',
        'awaiting_payment' => 'Menunggu Pembayaran',
        'awaiting_confirmation' => 'Menunggu Konfirmasi',
        'paid' => 'Lunas',
        'waived' => 'Dibebaskan',
    ];

    /**
     * Guardian relation types to the child (per application).
     *
     * @var array<string, string>
     */
    public const GUARDIAN_RELATIONS = [
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'wali' => 'Wali',
    ];

    /**
     * Parent identity option lists shared by father/mother/guardian.
     *
     * @var list<string>
     */
    public const PARENT_STATUSES = ['Masih Hidup', 'Meninggal'];

    /** @var list<string> */
    public const EDUCATION_LEVELS = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3', 'Lainnya'];

    /** @var list<string> */
    public const INCOME_RANGES = [
        'Kurang dari Rp 1.000.000',
        'Rp 1.000.000 – Rp 3.000.000',
        'Rp 3.000.000 – Rp 5.000.000',
        'Rp 5.000.000 – Rp 10.000.000',
        'Lebih dari Rp 10.000.000',
    ];

    /**
     * Discount types a committee may grant (extensible by design).
     *
     * @var array<string, string>
     */
    public const DISCOUNT_TYPES = [
        'percent' => 'Persen (%)',
        'nominal' => 'Nominal (Rp)',
    ];

    protected $fillable = [
        'user_id',
        'school_unit_id',
        'admission_wave_id',
        'admission_track_id',
        'major_id',
        'academic_year_id',
        'child_name',
        'birth_place',
        'birth_date',
        'gender',
        'parent_name',
        'parent_phone',
        'guardian_relation',
        'child_status_id',
        'create_parent_account',
        'father_data',
        'mother_data',
        'has_guardian',
        'guardian_data',
        'answers',
        'selection_scores',
        'selection_total',
        'form_fee_amount',
        'form_fee_status',
        'form_fee_discount_type',
        'form_fee_discount_value',
        'form_fee_request_kind',
        'form_fee_request_type',
        'form_fee_request_value',
        'form_fee_request_reason',
        'form_fee_request_file_path',
        'form_fee_request_status',
        'form_fee_decision_note',
        'form_fee_decided_by',
        'form_fee_decided_at',
        'status',
        'registration_number',
        'submitted_at',
        'verification_note',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'father_data' => 'array',
            'mother_data' => 'array',
            'guardian_data' => 'array',
            'has_guardian' => 'boolean',
            'answers' => 'array',
            'selection_scores' => 'array',
            'form_fee_decided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Additional guardian accounts sharing access to this
     * application (e.g. a parent account created from the form).
     */
    public function linkedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'application_access')
            ->withPivot('relation')
            ->withTimestamps();
    }

    /**
     * Whether the given user owns or shares access to this
     * application (guardian area authorization).
     */
    public function hasGuardianAccess(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->user_id === $user->id
            || $this->linkedUsers()->whereKey($user->id)->exists();
    }

    /**
     * The child's status (Yatim, Anak Guru, ...) verified by
     * the committee; null means the default Reguler status.
     */
    public function childStatus(): BelongsTo
    {
        return $this->belongsTo(ChildStatus::class);
    }

    /**
     * The status id fee/point resolution should use: the
     * stored status, or the Reguler master row when the
     * guardian never chose one.
     */
    public function effectiveChildStatusId(): ?int
    {
        return $this->child_status_id
            ?? ChildStatus::where('name', ChildStatus::DEFAULT_NAME)->value('id');
    }

    /**
     * Display label for the child's status.
     */
    public function childStatusLabel(): string
    {
        return $this->childStatus?->name ?? ChildStatus::DEFAULT_NAME;
    }

    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    public function admissionWave(): BelongsTo
    {
        return $this->belongsTo(AdmissionWave::class);
    }

    public function admissionTrack(): BelongsTo
    {
        return $this->belongsTo(AdmissionTrack::class);
    }

    /**
     * The effective announcement time for this application: the
     * per-track time set on the wave–track pivot when filled,
     * otherwise the wave's global announcement time. Null means
     * the announcement is not scheduled yet.
     */
    public function announcementAt(): ?Carbon
    {
        $wave = $this->admissionWave;

        if ($wave === null) {
            return null;
        }

        $track = $wave->admissionTracks()
            ->where('admission_tracks.id', $this->admission_track_id)
            ->first();

        $perTrack = $track?->pivot?->announcement_at;

        return $perTrack !== null && $perTrack !== ''
            ? Carbon::parse($perTrack)
            : $wave->announcement_at;
    }

    /**
     * Whether this application's result may already be shown to
     * the guardian: an effective announcement time is set and
     * has been reached.
     */
    public function resultsAnnounced(): bool
    {
        $announcementAt = $this->announcementAt();

        return $announcementAt !== null && ! now()->lt($announcementAt);
    }

    /**
     * The nearest upcoming schedule a guardian should know
     * about this application: the results announcement, the
     * next unpaid installment's due date, or the wave's
     * closing date — whichever comes first.
     *
     * @return array{label: string, at: \Carbon\Carbon}|null
     */
    public function nextSchedule(): ?array
    {
        $candidates = [];

        $announcementAt = $this->announcementAt();

        if ($announcementAt !== null && now()->lt($announcementAt)
            && in_array($this->status, ['submitted', 'verified', 'needs_revision', 'passed', 'failed'], true)) {
            $candidates[] = ['label' => 'Pengumuman hasil', 'at' => $announcementAt];
        }

        $wave = $this->admissionWave;

        if ($wave !== null && $wave->closes_at !== null && now()->lt($wave->closes_at)
            && in_array($this->status, ['awaiting_form_fee', 'filling_form', 'uploading_documents'], true)) {
            $candidates[] = ['label' => 'Pendaftaran gelombang ditutup', 'at' => $wave->closes_at];
        }

        $installment = $this->invoice?->installments
            ->where('status', '!=', 'paid')
            ->sortBy('due_date')
            ->first();

        if ($installment !== null) {
            $candidates[] = ['label' => 'Jatuh tempo termin '.$installment->sequence, 'at' => $installment->due_date];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $candidates[0];
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function formFeeDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'form_fee_decided_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The discount amount in rupiah granted on the form fee.
     */
    public function discountAmount(): int
    {
        if ($this->form_fee_discount_type === 'percent' && $this->form_fee_discount_value) {
            return intdiv($this->form_fee_amount * $this->form_fee_discount_value, 100);
        }

        if ($this->form_fee_discount_type === 'nominal' && $this->form_fee_discount_value) {
            return min($this->form_fee_discount_value, $this->form_fee_amount);
        }

        return 0;
    }

    /**
     * The amount the guardian still has to pay for the form fee.
     */
    public function payableAmount(): int
    {
        return max(0, $this->form_fee_amount - $this->discountAmount());
    }

    /**
     * Whether the fee stage is settled (free, paid, or waived), which
     * unlocks the form step.
     */
    public function feeSettled(): bool
    {
        return in_array($this->form_fee_status, ['free', 'paid', 'waived'], true);
    }

    /**
     * Whether the guardian may edit the form right now: the fee is
     * settled, the application is in the form/documents stage, and
     * the wave has not closed.
     */
    public function isEditable(): bool
    {
        return $this->feeSettled()
            && in_array($this->status, ['filling_form', 'uploading_documents', 'needs_revision'], true)
            && $this->admissionWave !== null
            && $this->admissionWave->status() !== AdmissionWave::STATUS_CLOSED;
    }

    /**
     * The human readable label of the current flow status.
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * The human readable label of the form fee status.
     */
    public function feeStatusLabel(): string
    {
        return self::FEE_STATUSES[$this->form_fee_status] ?? $this->form_fee_status;
    }
}
