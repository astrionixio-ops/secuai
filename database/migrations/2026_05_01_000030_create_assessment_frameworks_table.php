<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pivot: which frameworks an assessment is being evaluated against.
        // Intentionally no updated_at — Eloquent ->withTimestamps() must NOT be used on this pivot.
        Schema::create('assessment_frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained()->cascadeOnDelete();
            $table->string('scope')->default('full'); // full|partial|custom
            $table->json('included_controls')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['assessment_id', 'framework_id']);
            $table->index('framework_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_frameworks');
    }
};
