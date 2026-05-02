<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('frameworks', function (Blueprint $table) {
            $table->id();
            // Frameworks are global (system-seeded), not tenant-scoped.
            $table->string('code')->unique(); // soc2|iso27001|hipaa|pci_dss|nist_csf|gdpr
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('issuer')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // security|privacy|industry
            $table->string('region')->nullable(); // us|eu|global
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frameworks');
    }
};
