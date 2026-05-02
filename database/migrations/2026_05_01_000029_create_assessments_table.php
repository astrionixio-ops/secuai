<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft|in_progress|review|completed|archived
            $table->string('assessment_type')->default('internal'); // internal|external|self|third_party
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('target_date')->nullable();
            $table->integer('controls_total')->default(0);
            $table->integer('controls_passing')->default(0);
            $table->integer('controls_failing')->default(0);
            $table->integer('controls_not_applicable')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
