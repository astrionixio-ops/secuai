<?php

return [
    /*
     * Whether to require email verification before allowing login.
     * Recommended: true in production. Set false only for local dev with seeded users.
     */
    'require_email_verification' => env('REQUIRE_EMAIL_VERIFICATION', true),

    /*
     * Allow tenant resolution via subdomain (acme.secuai.com → tenant 'acme').
     * Off by default; enable when you set up wildcard DNS + Nginx.
     */
    'tenant_subdomain_routing' => env('TENANT_SUBDOMAIN_ROUTING', false),

    /*
     * Demo mode — seeds demo data and allows demo logins. Off in production.
     */
    'demo_mode_enabled' => env('DEMO_MODE_ENABLED', false),

    /*
     * Trial duration for new tenants (days).
     */
    'trial_days' => (int) env('TRIAL_DAYS', 14),

    /*
     * Field-level encryption key for cloud_credentials.secret_encrypted etc.
     * 32 bytes, base64-encoded. Generate: php -r "echo base64_encode(random_bytes(32));"
     */
    'secrets_encryption_key' => env('SECRETS_ENCRYPTION_KEY'),

    /*
     * Plan-tier feature gates. Used by middleware/policies in Phase 4.
     */
    'plan_limits' => [
        'starter' => ['max_users' => 5, 'max_findings' => 1000, 'max_assessments' => 3],
        'pro' => ['max_users' => 25, 'max_findings' => 25000, 'max_assessments' => 25],
        'business' => ['max_users' => 100, 'max_findings' => 250000, 'max_assessments' => 100],
        'enterprise' => ['max_users' => null, 'max_findings' => null, 'max_assessments' => null],
    ],
];
