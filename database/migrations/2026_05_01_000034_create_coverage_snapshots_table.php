<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coverage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_date');
            $table->integer('controls_total')->default(0);
            $table->integer('controls_covered')->default(0);
            $table->integer('controls_partial')->default(0);
            $table->integer('controls_uncovered')->default(0);
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->integer('open_findings')->default(0);
            $table->integer('critical_findings')->default(0);
            $table->json('breakdown')->nullable(); // per-domain breakdown
            $table->timestamps();

            $table->index(['tenant_id', 'snapshot_date']);
            $table->index(['tenant_id', 'framework_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_snapshots');
    }
};
