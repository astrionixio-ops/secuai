<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'owner_id',
        'title',
        'document_type',
        'category',
        'status',
        'version',
        'summary',
        'content',
        'file_path',
        'file_hash',
        'file_size',
        'mime_type',
        'effective_date',
        'next_review_date',
        'metadata',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'next_review_date' => 'date',
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
