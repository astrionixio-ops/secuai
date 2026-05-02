<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Framework extends Model
{
    use HasFactory;

    // Frameworks are global, system-seeded reference data — no tenant scoping.

    protected $fillable = [
        'code',
        'name',
        'version',
        'issuer',
        'description',
        'category',
        'region',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class);
    }

    public function assessments(): BelongsToMany
    {
        // assessment_frameworks pivot has no updated_at -> do NOT use ->withTimestamps()
        return $this->belongsToMany(Assessment::class, 'assessment_frameworks')
            ->withPivot(['scope', 'included_controls', 'created_at']);
    }
}
