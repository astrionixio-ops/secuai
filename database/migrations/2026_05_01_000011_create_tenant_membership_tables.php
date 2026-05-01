<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // tenant_members: who belongs to which tenant, in what role.
        // SECURITY: roles MUST live here, never on users — putting roles on the
        // user row is the classic privilege-escalation footgun.
        Schema::create('tenant_members', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->char('user_id', 36);
            $table->string('role', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('user_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role')->references('value')->on('enum_tenant_role');
        });

        // tenant_invites: invite-by-email-token flow.
        Schema::create('tenant_invites', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->string('email');
            $table->string('role', 32);
            $table->string('token', 128)->unique();
            $table->char('invited_by', 36);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'email']);
            $table->index('expires_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users');
            $table->foreign('role')->references('value')->on('enum_tenant_role');
        });

        // profiles: per-user display info (separate from auth fields on users).
        Schema::create('profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique();
            $table->string('display_name')->nullable();
            $table->string('avatar_url', 1024)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // user_roles: GLOBAL (cross-tenant) roles — e.g. internal Astrionix staff.
        // Tenant-scoped roles live on tenant_members. Don't conflate.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->string('role', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'role']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role')->references('value')->on('enum_app_role');
        });

        // cookie_consent: GDPR — per-user analytics/marketing opt-in.
        Schema::create('cookie_consent', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consent');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('tenant_invites');
        Schema::dropIfExists('tenant_members');
    }
};
