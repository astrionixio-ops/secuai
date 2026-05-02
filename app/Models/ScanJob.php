<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanJob extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'environment_id',
        'cloud_credential_id',
        'initiated_by',
        'scan_type',
        'status',
        'assets_scanned',
        'findings_count',
        'progress_percent',
        'scope',
        'summary',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function cloudCredential(): BelongsTo
    {
        return $this->belongsTo(CloudCredential::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
