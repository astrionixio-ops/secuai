<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'environment_id',
        'cloud_credential_id',
        'external_id',
        'provider',
        'asset_type',
        'name',
        'region',
        'status',
        'criticality',
        'tags',
        'configuration',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'configuration' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
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

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
