<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiSummary extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'ai_summaries';

    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'summary_type',
        'prompt',
        'content',
        'model',
        'input_tokens',
        'output_tokens',
        'metadata',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Polymorphic subject. subject_type stores a short tag (scan_job, finding, ...) rather than a class
     * name so that the storage stays stable if the App\Models namespace changes.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }
}
