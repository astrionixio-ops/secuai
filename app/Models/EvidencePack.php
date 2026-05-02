<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidencePack extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'assessment_id',
        'framework_id',
        'built_by',
        'name',
        'description',
        'status',
        'evidence_ids',
        'evidence_count',
        'file_path',
        'file_hash',
        'file_size',
        'built_at',
        'expires_at',
    ];

    protected $casts = [
        'evidence_ids' => 'array',
        'file_size' => 'integer',
        'built_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function builder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'built_by');
    }
}
