<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scan_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cloud_credential_id')->nullable()->constrained('cloud_credentials')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scan_type')->default('full'); // full|incremental|targeted|simulated
            $table->string('status')->default('pending'); // pending|running|completed|failed|cancelled
            $table->integer('assets_scanned')->default(0);
            $table->integer('findings_count')->default(0);
            $table->integer('progress_percent')->default(0);
            $table->json('scope')->nullable(); // which providers, regions, asset types
            $table->json('summary')->nullable(); // result summary
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scan_type']);
            $table->index(['tenant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_jobs');
    }
};
