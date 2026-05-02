<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cloud_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider'); // aws|azure|gcp|do|linode|other
            $table->string('account_identifier')->nullable(); // AWS account id, Azure tenant id, etc.
            $table->string('region')->nullable();
            $table->text('encrypted_payload'); // Laravel Crypt encrypted JSON of secrets
            $table->string('payload_fingerprint', 64)->nullable(); // sha256 hash for change detection
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_credentials');
    }
};
