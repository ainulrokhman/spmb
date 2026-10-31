<?php

namespace App\Contracts;

use App\Models\DocumentType;

interface DocumentTypeServiceInterface
{
    /**
     * Store a newly created document type.
     */
    public function createDocumentType(array $data): DocumentType;

    /**
     * Update the specified document type.
     */
    public function updateDocumentType(DocumentType $documentType, array $data): DocumentType;

    /**
     * Delete the specified document type.
     */
    public function deleteDocumentType(DocumentType $documentType): bool;
}
