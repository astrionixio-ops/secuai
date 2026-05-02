<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Finding extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'scan_job_id',
        'asset_id',
        'rule_id',
        'title',
        'description',
        'remediation',
        'severity',
        'status',
        'category',
        'evidence',
        'external_ref',
        'assigned_to',
        'detected_at',
        'resolved_at',
        'suppressed_until',
    ];

    protected $casts = [
        'evidence' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'suppressed_until' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scanJob(): BelongsTo
    {
        return $this->belongsTo(ScanJob::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function controls(): BelongsToMany
    {
        // finding_controls pivot has no updated_at -> do NOT use ->withTimestamps()
        return $this->belongsToMany(Control::class, 'finding_controls')
            ->withPivot(['relevance', 'created_at']);
    }
}
