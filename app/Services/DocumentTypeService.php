<?php

namespace App\Services;

use App\Contracts\DocumentTypeServiceInterface;
use App\Models\DocumentType;

class DocumentTypeService implements DocumentTypeServiceInterface
{
    /**
     * Store a newly created document type.
     */
    public function createDocumentType(array $data): DocumentType
    {
        return DocumentType::create($this->typeAttributes($data));
    }

    /**
     * Update the specified document type.
     */
    public function updateDocumentType(DocumentType $documentType, array $data): DocumentType
    {
        $documentType->update($this->typeAttributes($data));

        return $documentType;
    }

    /**
     * Delete the specified document type.
     */
    public function deleteDocumentType(DocumentType $documentType): bool
    {
        return (bool) $documentType->delete();
    }

    /**
     * Map validated form data to model attributes.
     *
     * Extensions are normalized to a lowercase comma-separated list so
     * upload validation later can compare them reliably.
     */
    private function typeAttributes(array $data): array
    {
        $extensions = collect(explode(',', $data['allowed_extensions']))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->implode(',');

        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'allowed_extensions' => $extensions,
            'max_size_kb' => $data['max_size_kb'],
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
