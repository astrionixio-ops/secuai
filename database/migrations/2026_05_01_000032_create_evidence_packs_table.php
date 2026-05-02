<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('framework_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('built_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('building'); // building|ready|delivered|expired
            $table->json('evidence_ids')->nullable(); // snapshot of included evidence
            $table->integer('evidence_count')->default(0);
            $table->string('file_path')->nullable(); // resulting bundle (zip)
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_packs');
    }
};
