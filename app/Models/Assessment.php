<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'owner_id',
        'name',
        'description',
        'status',
        'assessment_type',
        'period_start',
        'period_end',
        'target_date',
        'controls_total',
        'controls_passing',
        'controls_failing',
        'controls_not_applicable',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
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

    public function frameworks(): BelongsToMany
    {
        // assessment_frameworks pivot has no updated_at -> do NOT use ->withTimestamps()
        return $this->belongsToMany(Framework::class, 'assessment_frameworks')
            ->withPivot(['scope', 'included_controls', 'created_at']);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function evidencePacks(): HasMany
    {
        return $this->hasMany(EvidencePack::class);
    }
}
