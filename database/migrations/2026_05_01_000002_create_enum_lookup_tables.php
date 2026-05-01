<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL native ENUM is painful to ALTER (blocking + table rebuild on some versions).
 * We use VARCHAR(32) + a CHECK constraint via these lookup tables. To add a new value:
 *   INSERT INTO enum_app_role (value) VALUES ('new_role');
 * No ALTER needed.
 */
return new class extends Migration {
    /** @var array<string, array<int, string>> */
    private array $enums = [
        'enum_app_role' => ['admin', 'auditor', 'analyst', 'viewer'],
        'enum_tenant_role' => ['admin', 'auditor', 'analyst', 'viewer'],
        'enum_workspace_mode' => ['demo', 'production'],
        'enum_plan_tier' => ['starter', 'pro', 'business', 'enterprise'],
        'enum_subscription_status' => ['trialing', 'active', 'past_due', 'canceled', 'expired'],
        'enum_severity' => ['critical', 'high', 'medium', 'low', 'info'],
        'enum_finding_status' => ['open', 'in_progress', 'remediated', 'accepted', 'false_positive'],
        'enum_assessment_status' => ['draft', 'in_progress', 'completed', 'archived'],
        'enum_environment_type' => ['aws', 'azure', 'gcp', 'on_prem', 'kubernetes', 'saas', 'other'],
        'enum_evidence_type' => ['screenshot', 'document', 'log', 'config', 'other'],
        'enum_evidence_pack_status' => ['draft', 'submitted', 'approved', 'rejected', 'revision_requested'],
        'enum_gap_status' => ['open', 'in_remediation', 'evidence_requested', 'risk_accepted', 'closed'],
        'enum_risk_level' => ['low', 'medium', 'high', 'critical'],
        'enum_risk_status' => ['open', 'mitigating', 'accepted', 'transferred', 'closed'],
        'enum_task_status' => ['todo', 'in_progress', 'done', 'blocked'],
        'enum_policy_status' => ['draft', 'active', 'retired'],
        'enum_policy_trigger' => [
            'finding_created', 'finding_updated', 'pack_submitted',
            'pack_approved', 'pack_rejected', 'review_due', 'manual',
        ],
        'enum_policy_action_kind' => [
            'create_remediation_task', 'log_activity', 'require_approval',
            'notify', 'create_finding',
        ],
        'enum_summary_type' => ['executive', 'audit_ready', 'control_narrative', 'remediation_brief'],
        'enum_component_status' => ['operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance'],
        'enum_incident_status' => ['investigating', 'identified', 'monitoring', 'resolved'],
        'enum_incident_severity' => ['minor', 'major', 'critical'],
        'enum_job_kind' => ['webhook', 'edge_function', 'notification'],
        'enum_job_run_status' => ['pending', 'running', 'success', 'failed', 'skipped'],
    ];

    public function up(): void
    {
        foreach (array_keys($this->enums) as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('value', 32)->primary();
                $table->string('label', 128)->nullable();
                $table->integer('sort_order')->default(0);
            });
        }

        foreach ($this->enums as $name => $values) {
            $rows = [];
            foreach ($values as $i => $v) {
                $rows[] = ['value' => $v, 'label' => $v, 'sort_order' => $i];
            }
            DB::table($name)->insert($rows);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->enums) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
