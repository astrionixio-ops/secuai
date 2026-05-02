<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CloudCredential extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'environment_id',
        'name',
        'provider',
        'account_identifier',
        'region',
        'encrypted_payload',
        'payload_fingerprint',
        'rotated_at',
        'last_used_at',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'encrypted_payload',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function scanJobs(): HasMany
    {
        return $this->hasMany(ScanJob::class);
    }

    /**
     * Set the credential secret payload. Stored encrypted at rest.
     */
    public function setSecretPayload(array $payload): void
    {
        $json = json_encode($payload);
        $this->encrypted_payload = Crypt::encryptString($json);
        $this->payload_fingerprint = hash('sha256', $json);
    }

    /**
     * Decrypt and return the credential secret payload. Use sparingly.
     */
    public function getSecretPayload(): array
    {
        if (! $this->encrypted_payload) {
            return [];
        }
        $decrypted = Crypt::decryptString($this->encrypted_payload);
        $decoded = json_decode($decrypted, true);
        return is_array($decoded) ? $decoded : [];
    }
}
