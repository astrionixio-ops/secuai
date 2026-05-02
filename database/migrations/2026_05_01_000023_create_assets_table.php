<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cloud_credential_id')->nullable()->constrained('cloud_credentials')->nullOnDelete();
            $table->string('external_id')->nullable(); // ARN, resource id, etc.
            $table->string('provider'); // aws|azure|gcp
            $table->string('asset_type'); // ec2|s3|rds|iam_user|sg|...
            $table->string('name')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('active'); // active|terminated|unknown
            $table->string('criticality')->default('medium'); // low|medium|high|critical
            $table->json('tags')->nullable();
            $table->json('configuration')->nullable(); // raw config snapshot
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider', 'asset_type']);
            $table->index(['tenant_id', 'environment_id']);
            $table->index(['tenant_id', 'criticality']);
            $table->unique(['tenant_id', 'provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
