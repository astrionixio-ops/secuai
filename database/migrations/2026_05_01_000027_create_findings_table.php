<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_id')->nullable(); // rule that fired
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('remediation')->nullable();
            $table->string('severity'); // low|medium|high|critical
            $table->string('status')->default('open'); // open|in_progress|resolved|suppressed|false_positive
            $table->string('category')->nullable(); // misconfiguration|vulnerability|policy|access
            $table->json('evidence')->nullable(); // raw evidence blob
            $table->string('external_ref')->nullable(); // CVE, CIS ref, etc.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('suppressed_until')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'severity']);
            $table->index(['tenant_id', 'asset_id']);
            $table->index(['tenant_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
