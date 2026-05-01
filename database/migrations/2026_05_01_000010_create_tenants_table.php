<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('logo_url', 1024)->nullable();
            $table->string('primary_color', 16)->nullable();

            // Enums (FK'd to lookup tables for referential integrity).
            $table->string('mode', 32)->default('production');
            $table->string('plan', 32)->default('starter');
            $table->string('subscription_status', 32)->default('trialing');

            $table->timestamp('trial_started_at')->useCurrent();
            $table->timestamp('trial_ends_at');
            $table->timestamps();

            $table->foreign('mode')->references('value')->on('enum_workspace_mode');
            $table->foreign('plan')->references('value')->on('enum_plan_tier');
            $table->foreign('subscription_status')->references('value')->on('enum_subscription_status');

            $table->index('subscription_status');
            $table->index('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
