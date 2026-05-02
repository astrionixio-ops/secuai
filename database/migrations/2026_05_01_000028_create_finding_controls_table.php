<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pivot: which findings violate which controls.
        // Intentionally no updated_at — Eloquent ->withTimestamps() must NOT be used on this pivot.
        Schema::create('finding_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->string('relevance')->default('direct'); // direct|partial|inferred
            $table->timestamp('created_at')->nullable();

            $table->unique(['finding_id', 'control_id']);
            $table->index('control_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_controls');
    }
};
