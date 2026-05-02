<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            // Controls belong to a framework and are global (system-seeded).
            $table->foreignId('framework_id')->constrained()->cascadeOnDelete();
            $table->string('control_ref'); // e.g. CC1.1, A.5.1, 164.308(a)(1)
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('domain')->nullable(); // grouping within framework
            $table->string('severity')->default('medium'); // low|medium|high|critical
            $table->json('mappings')->nullable(); // cross-framework mappings
            $table->json('implementation_guidance')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['framework_id', 'domain']);
            $table->index(['framework_id', 'severity']);
            $table->unique(['framework_id', 'control_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controls');
    }
};
