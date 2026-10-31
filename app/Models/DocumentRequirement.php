<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    use HasFactory;

    /** The document must be uploaded by the applicant. */
    public const REQUIRED = 'required';

    /** The document may be uploaded by the applicant. */
    public const OPTIONAL = 'optional';

    /**
     * All requirement levels a unit can assign to a document type.
     *
     * @var array<int, string>
     */
    public const LEVELS = [
        self::REQUIRED,
        self::OPTIONAL,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'document_type_id',
        'requirement',
    ];

    /**
     * The school unit this requirement belongs to.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * The document type this requirement applies to.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
