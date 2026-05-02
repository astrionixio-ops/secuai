<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'evidence';

    protected $fillable = [
        'tenant_id',
        'assessment_id',
        'control_id',
        'uploaded_by',
        'title',
        'description',
        'evidence_type',
        'source',
        'file_path',
        'file_hash',
        'file_size',
        'mime_type',
        'payload',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'file_size' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
