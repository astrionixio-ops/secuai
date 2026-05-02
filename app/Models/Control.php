<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Control extends Model
{
    use HasFactory;

    // Controls are global, system-seeded reference data — no tenant scoping.

    protected $fillable = [
        'framework_id',
        'control_ref',
        'title',
        'description',
        'domain',
        'severity',
        'mappings',
        'implementation_guidance',
        'is_active',
    ];

    protected $casts = [
        'mappings' => 'array',
        'implementation_guidance' => 'array',
        'is_active' => 'boolean',
    ];

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function findings(): BelongsToMany
    {
        // finding_controls pivot has no updated_at -> do NOT use ->withTimestamps()
        return $this->belongsToMany(Finding::class, 'finding_controls')
            ->withPivot(['relevance', 'created_at']);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
