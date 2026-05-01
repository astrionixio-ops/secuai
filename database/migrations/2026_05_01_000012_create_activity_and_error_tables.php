<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // activity_log: every state-changing action. Required for SOC2 audit trail.
        // Indexed so the audit log UI can filter by tenant + time range fast.
        Schema::create('activity_log', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->char('actor_id', 36)->nullable();
            $table->string('action', 128);
            $table->string('entity_type', 64)->nullable();
            $table->char('entity_id', 36)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'actor_id', 'created_at']);
        });

        // error_events: client + server errors. Frontend posts here via /api/log-error.
        Schema::create('error_events', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->nullable();
            $table->char('user_id', 36)->nullable();
            $table->string('source', 32);   // 'web', 'api', 'job', 'scan', etc.
            $table->string('level', 16);    // 'error', 'warn', 'info'
            $table->text('message');
            $table->longText('stack')->nullable();
            $table->string('url', 1024)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['level', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
        Schema::dropIfExists('activity_log');
    }
};
