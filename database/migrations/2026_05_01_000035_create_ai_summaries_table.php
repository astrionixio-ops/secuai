<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type'); // morphable: scan_job|finding|assessment|coverage_snapshot|...
            $table->unsignedBigInteger('subject_id');
            $table->string('summary_type')->default('overview'); // overview|remediation|risk|exec
            $table->text('prompt')->nullable();
            $table->longText('content');
            $table->string('model')->nullable(); // model identifier used
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'ai_summaries_subject_idx');
            $table->index(['tenant_id', 'summary_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
