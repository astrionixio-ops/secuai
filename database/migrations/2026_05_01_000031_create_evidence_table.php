<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('control_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('evidence_type'); // document|screenshot|log|config|attestation|scan_result
            $table->string('source')->default('manual'); // manual|automated|api|scan
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('payload')->nullable(); // for non-file evidence
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('active'); // active|expired|superseded|rejected
            $table->timestamps();

            $table->index(['tenant_id', 'evidence_type']);
            $table->index(['tenant_id', 'control_id']);
            $table->index(['tenant_id', 'assessment_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};
