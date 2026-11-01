<?php

namespace App\Contracts;

use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\DocumentType;
use App\Models\Major;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface ApplicationServiceInterface
{
    /**
     * Start an application for a child: freezes the unit's form fee
     * and places the application at its first step.
     */
    public function start(User $guardian, AdmissionWave $wave, AdmissionTrack $track, ?Major $major): Application;

    /**
     * Record a guardian's request for a form fee waiver or discount.
     */
    public function requestFeeRelief(Application $application, string $kind, ?string $type, ?int $value, string $reason): Application;

    /**
     * Decide a pending fee relief request (committee side).
     */
    public function decideFeeRequest(Application $application, bool $approve, User $decider, ?string $note): Application;

    /**
     * Mark the form fee as paid and unlock the form step.
     */
    public function settleFeeByPayment(Application $application): Application;

    /**
     * Save the application form (core identity, parent identities,
     * dynamic answers) and advance to the documents step.
     */
    public function saveForm(Application $application, array $data): Application;

    /**
     * Store (or replace) one uploaded document of the application.
     */
    public function storeDocument(Application $application, DocumentType $documentType, UploadedFile $file): void;

    /**
     * Delete one uploaded document of the application.
     */
    public function deleteDocument(Application $application, DocumentType $documentType): void;

    /**
     * Required document types of the application's unit that have not
     * been uploaded yet.
     *
     * @return Collection<int, DocumentType>
     */
    public function missingRequiredDocuments(Application $application): Collection;

    /**
     * Submit the application: validates the step order, completeness,
     * wave state, and quota, then issues the registration number.
     * Resubmission after a revision request keeps the number.
     */
    public function submit(Application $application): Application;

    /**
     * Record the committee's check of one uploaded document
     * (verified, or revision with a note).
     */
    public function verifyDocument(\App\Models\ApplicationDocument $document, string $status, ?string $note, User $verifier): \App\Models\ApplicationDocument;

    /**
     * Required document types whose upload is missing or not yet
     * verified — these block the overall "verified" decision.
     *
     * @return Collection<int, DocumentType>
     */
    public function unverifiedRequiredDocuments(Application $application): Collection;

    /**
     * Record the committee's overall decision: verified (only when
     * every required document is verified), needs_revision, or
     * rejected — the latter two require a note for the guardian.
     */
    public function decideVerification(Application $application, string $decision, ?string $note, User $decider): Application;
}
