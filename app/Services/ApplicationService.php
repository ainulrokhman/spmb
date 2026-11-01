<?php

namespace App\Services;

use App\Contracts\ApplicationServiceInterface;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Major;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ApplicationService implements ApplicationServiceInterface
{
    /**
     * Start an application for a child: freezes the unit's form fee
     * and places the application at its first step.
     */
    public function start(User $guardian, AdmissionWave $wave, AdmissionTrack $track, ?Major $major): Application
    {
        if ($wave->status() !== AdmissionWave::STATUS_OPEN) {
            throw new InvalidArgumentException('Gelombang yang dipilih tidak sedang dibuka.');
        }

        if (! $wave->admissionTracks()->whereKey($track->id)->exists()) {
            throw new InvalidArgumentException('Jalur yang dipilih tidak dibuka pada gelombang ini.');
        }

        if ($major !== null && $major->school_unit_id !== $wave->school_unit_id) {
            throw new InvalidArgumentException('Jurusan yang dipilih tidak dimiliki unit ini.');
        }

        $unit = $wave->schoolUnit;
        $fee = (int) $unit->form_fee;
        $isFree = $fee === 0;

        return Application::create([
            'user_id' => $guardian->id,
            'school_unit_id' => $wave->school_unit_id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'major_id' => $major?->id,
            'academic_year_id' => $wave->academic_year_id,
            'form_fee_amount' => $fee,
            'form_fee_status' => $isFree ? 'free' : 'unpaid',
            'status' => $isFree ? 'filling_form' : 'awaiting_form_fee',
        ]);
    }

    /**
     * Record a guardian's request for a form fee waiver or discount.
     */
    public function requestFeeRelief(Application $application, string $kind, ?string $type, ?int $value, string $reason): Application
    {
        if ($application->feeSettled() || $application->status !== 'awaiting_form_fee') {
            throw new InvalidArgumentException('Pengajuan hanya dapat dilakukan sebelum biaya formulir diselesaikan.');
        }

        if ($application->form_fee_request_status === 'pending') {
            throw new InvalidArgumentException('Masih ada pengajuan yang menunggu keputusan panitia.');
        }

        $application->update([
            'form_fee_request_kind' => $kind,
            'form_fee_request_type' => $kind === 'discount' ? $type : null,
            'form_fee_request_value' => $kind === 'discount' ? $value : null,
            'form_fee_request_reason' => $reason,
            'form_fee_request_status' => 'pending',
            'form_fee_decision_note' => null,
        ]);

        return $application;
    }

    /**
     * Decide a pending fee relief request (committee side).
     */
    public function decideFeeRequest(Application $application, bool $approve, User $decider, ?string $note): Application
    {
        if ($application->form_fee_request_status !== 'pending') {
            throw new InvalidArgumentException('Tidak ada pengajuan yang menunggu keputusan.');
        }

        $application->form_fee_request_status = $approve ? 'approved' : 'rejected';
        $application->form_fee_decision_note = $note;
        $application->form_fee_decided_by = $decider->id;
        $application->form_fee_decided_at = now();

        if ($approve) {
            if ($application->form_fee_request_kind === 'waiver') {
                $application->form_fee_status = 'waived';
                $application->status = 'filling_form';
            } else {
                $application->form_fee_discount_type = $application->form_fee_request_type;
                $application->form_fee_discount_value = $application->form_fee_request_value;

                if ($application->payableAmount() === 0) {
                    $application->form_fee_status = 'waived';
                    $application->status = 'filling_form';
                } else {
                    // The guardian still pays the remainder.
                    $application->form_fee_status = 'unpaid';
                }
            }
        }

        $application->save();

        return $application;
    }

    /**
     * Mark the form fee as paid and unlock the form step.
     */
    public function settleFeeByPayment(Application $application): Application
    {
        $application->form_fee_status = 'paid';

        if ($application->status === 'awaiting_form_fee') {
            $application->status = 'filling_form';
        }

        $application->save();

        return $application;
    }

    /**
     * Save the application form (core identity, parent identities,
     * dynamic answers) and advance to the documents step.
     */
    public function saveForm(Application $application, array $data): Application
    {
        if (! $application->isEditable()) {
            throw new InvalidArgumentException('Formulir belum dapat diisi pada tahap ini.');
        }

        $application->update([
            'child_name' => $data['child_name'],
            'birth_place' => $data['birth_place'],
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'parent_name' => $data['parent_name'],
            'parent_phone' => $data['parent_phone'],
            'guardian_relation' => $data['guardian_relation'],
            'father_data' => $data['father'],
            'mother_data' => $data['mother'],
            'has_guardian' => (bool) ($data['has_guardian'] ?? false),
            'guardian_data' => ($data['has_guardian'] ?? false) ? $data['guardian'] : null,
            'answers' => $data['answers'] ?? [],
            'status' => 'uploading_documents',
        ]);

        return $application;
    }

    /**
     * Store (or replace) one uploaded document of the application.
     */
    public function storeDocument(Application $application, DocumentType $documentType, UploadedFile $file): void
    {
        $existing = $application->documents()->where('document_type_id', $documentType->id)->first();

        if ($existing !== null) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $fileName = str($documentType->code)->slug().'-'.$application->id.'.'.$extension;
        $path = $file->storeAs('berkas-pendaftaran/'.$application->id, $fileName, 'public');

        $application->documents()->updateOrCreate(
            ['document_type_id' => $documentType->id],
            [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                // A (re)upload always restarts this document's check.
                'verification_status' => 'pending',
                'verification_note' => null,
                'verified_by' => null,
                'verified_at' => null,
            ]
        );
    }

    /**
     * Delete one uploaded document of the application.
     */
    public function deleteDocument(Application $application, DocumentType $documentType): void
    {
        $existing = $application->documents()->where('document_type_id', $documentType->id)->first();

        if ($existing !== null) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->delete();
        }
    }

    /**
     * Required document types of the application's unit that have not
     * been uploaded yet.
     *
     * @return Collection<int, DocumentType>
     */
    public function missingRequiredDocuments(Application $application): Collection
    {
        $uploadedTypeIds = $application->documents()->pluck('document_type_id');

        return DocumentType::query()
            ->whereIn('id', DocumentRequirement::query()
                ->where('school_unit_id', $application->school_unit_id)
                ->where('requirement', DocumentRequirement::REQUIRED)
                ->pluck('document_type_id'))
            ->whereNotIn('id', $uploadedTypeIds)
            ->get();
    }

    /**
     * Submit the application: validates the step order, completeness,
     * wave state, and quota, then issues the registration number.
     */
    public function submit(Application $application): Application
    {
        if (! in_array($application->status, ['uploading_documents', 'needs_revision'], true)) {
            throw new InvalidArgumentException('Pendaftaran belum mencapai tahap pengiriman.');
        }

        $isResubmission = $application->registration_number !== null;

        $wave = $application->admissionWave;

        if ($wave->status() !== AdmissionWave::STATUS_OPEN) {
            throw new InvalidArgumentException('Gelombang sudah ditutup, pendaftaran tidak dapat dikirim.');
        }

        if ($this->missingRequiredDocuments($application)->isNotEmpty()) {
            throw new InvalidArgumentException('Berkas wajib belum lengkap.');
        }

        if (! $isResubmission && $wave->quota !== null) {
            $submittedCount = Application::query()
                ->where('admission_wave_id', $wave->id)
                ->whereNotNull('registration_number')
                ->count();

            if ($submittedCount >= $wave->quota) {
                throw new InvalidArgumentException('Kuota gelombang sudah penuh.');
            }
        }

        return DB::transaction(function () use ($application, $isResubmission) {
            $number = $application->registration_number;

            if (! $isResubmission) {
                // Serialize number issuance per school unit.
                SchoolUnit::query()->whereKey($application->school_unit_id)->lockForUpdate()->first();

                $sequence = Application::query()
                    ->where('school_unit_id', $application->school_unit_id)
                    ->where('academic_year_id', $application->academic_year_id)
                    ->whereNotNull('registration_number')
                    ->count() + 1;

                $yearCode = substr((string) $application->academicYear->name, 2, 2);
                $number = $application->schoolUnit->code
                    .$yearCode
                    .str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            }

            $application->update([
                'registration_number' => $number,
                'status' => 'submitted',
                'submitted_at' => now(),
                // A resubmission clears the previous decision record.
                'verification_note' => null,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            return $application;
        });
    }

    /**
     * Record the committee's check of one uploaded document
     * (verified, or revision with a note).
     */
    public function verifyDocument(ApplicationDocument $document, string $status, ?string $note, User $verifier): ApplicationDocument
    {
        $application = $document->application;

        if ($application->status !== 'submitted') {
            throw new InvalidArgumentException('Berkas hanya dapat diperiksa selama pendaftaran menunggu verifikasi.');
        }

        if (! in_array($status, ['verified', 'revision'], true)) {
            throw new InvalidArgumentException('Status pemeriksaan berkas tidak dikenal.');
        }

        if ($status === 'revision' && blank($note)) {
            throw new InvalidArgumentException('Catatan wajib diisi untuk berkas yang perlu perbaikan.');
        }

        $document->update([
            'verification_status' => $status,
            'verification_note' => $note,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);

        return $document;
    }

    /**
     * Required document types whose upload is missing or not yet
     * verified — these block the overall "verified" decision.
     *
     * @return Collection<int, DocumentType>
     */
    public function unverifiedRequiredDocuments(Application $application): Collection
    {
        $verifiedTypeIds = $application->documents()
            ->where('verification_status', 'verified')
            ->pluck('document_type_id');

        return DocumentType::query()
            ->whereIn('id', DocumentRequirement::query()
                ->where('school_unit_id', $application->school_unit_id)
                ->where('requirement', DocumentRequirement::REQUIRED)
                ->pluck('document_type_id'))
            ->whereNotIn('id', $verifiedTypeIds)
            ->get();
    }

    /**
     * Record the committee's overall decision: verified (only when
     * every required document is verified), needs_revision, or
     * rejected — the latter two require a note for the guardian.
     */
    public function decideVerification(Application $application, string $decision, ?string $note, User $decider): Application
    {
        if ($application->status !== 'submitted') {
            throw new InvalidArgumentException('Keputusan hanya dapat diambil selama pendaftaran menunggu verifikasi.');
        }

        if ($decision === 'verified') {
            $unverified = $this->unverifiedRequiredDocuments($application);

            if ($unverified->isNotEmpty()) {
                throw new InvalidArgumentException('Berkas wajib belum semua terverifikasi: '.$unverified->pluck('name')->implode(', ').'.');
            }
        } elseif (in_array($decision, ['needs_revision', 'rejected'], true)) {
            if (blank($note)) {
                throw new InvalidArgumentException('Catatan wajib diisi untuk keputusan ini.');
            }
        } else {
            throw new InvalidArgumentException('Keputusan tidak dikenal.');
        }

        $application->update([
            'status' => $decision,
            'verification_note' => $note,
            'verified_by' => $decider->id,
            'verified_at' => now(),
        ]);

        return $application;
    }

    /**
     * Announce the result of a verified application (Bab 5): 'passed'
     * (Lulus) or 'failed' (Tidak Lulus). Visibility to the guardian
     * is governed by the wave's announcement time.
     */
    public function decideAnnouncement(Application $application, string $decision): Application
    {
        if (! in_array($decision, ['passed', 'failed'], true)) {
            throw new InvalidArgumentException('Keputusan pengumuman tidak dikenal.');
        }

        if ($application->status !== 'verified') {
            throw new InvalidArgumentException('Hanya pendaftar Terverifikasi yang dapat ditetapkan hasilnya.');
        }

        $application->update(['status' => $decision]);

        return $application;
    }
}
