<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('document_type'); // policy|procedure|standard|report|sop|other
            $table->string('category')->nullable();
            $table->string('status')->default('draft'); // draft|review|approved|published|archived
            $table->string('version', 32)->default('1.0');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable(); // markdown / html body when stored inline
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('next_review_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'document_type']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
