<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageSnapshot extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'framework_id',
        'assessment_id',
        'snapshot_date',
        'controls_total',
        'controls_covered',
        'controls_partial',
        'controls_uncovered',
        'coverage_percent',
        'open_findings',
        'critical_findings',
        'breakdown',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'coverage_percent' => 'decimal:2',
        'breakdown' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
