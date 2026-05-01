-- SecuAI MySQL schema (translated from Supabase/Postgres)
-- Engine: InnoDB / utf8mb4
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS policy_events;
DROP TABLE IF EXISTS policy_acknowledgements;
DROP TABLE IF EXISTS policy_controls;
DROP TABLE IF EXISTS policy_rules;
DROP TABLE IF EXISTS policies;
DROP TABLE IF EXISTS coverage_snapshots;
DROP TABLE IF EXISTS evidence_pack_reviews;
DROP TABLE IF EXISTS evidence_packs;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS ai_summaries;
DROP TABLE IF EXISTS ai_insights;
DROP TABLE IF EXISTS integration_links;
DROP TABLE IF EXISTS integrations;
DROP TABLE IF EXISTS remediation_tasks;
DROP TABLE IF EXISTS finding_controls;
DROP TABLE IF EXISTS evidence;
DROP TABLE IF EXISTS findings;
DROP TABLE IF EXISTS scan_jobs;
DROP TABLE IF EXISTS assessment_frameworks;
DROP TABLE IF EXISTS assessments;
DROP TABLE IF EXISTS controls;
DROP TABLE IF EXISTS frameworks;
DROP TABLE IF EXISTS assets;
DROP TABLE IF EXISTS cloud_credentials;
DROP TABLE IF EXISTS environments;
DROP TABLE IF EXISTS organizations;
DROP TABLE IF EXISTS branding;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS tenant_invites;
DROP TABLE IF EXISTS tenant_members;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS tenants;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) NULL,
  email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE profiles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL UNIQUE,
  display_name VARCHAR(255) NULL,
  avatar_url TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_roles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  role ENUM('admin','user','moderator') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_role (user_id, role),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tenants (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  primary_color VARCHAR(16) DEFAULT '#3B82F6',
  logo_url TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tenant_members (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  role ENUM('admin','auditor','analyst','viewer') NOT NULL DEFAULT 'viewer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_user (tenant_id, user_id),
  CONSTRAINT fk_tm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tenant_invites (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  email VARCHAR(255) NOT NULL,
  role ENUM('admin','auditor','analyst','viewer') NOT NULL DEFAULT 'viewer',
  token VARCHAR(64) NOT NULL UNIQUE,
  invited_by CHAR(36) NOT NULL,
  accepted_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE branding (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL UNIQUE,
  product_name VARCHAR(255) DEFAULT 'SecuAI Enterprise',
  primary_color VARCHAR(16) DEFAULT '#3B82F6',
  accent_color  VARCHAR(16) DEFAULT '#8B5CF6',
  support_email VARCHAR(255) NULL,
  footer_text VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_branding_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  actor_id CHAR(36) NULL,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id CHAR(36) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_act_tenant (tenant_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE organizations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  industry VARCHAR(128) NULL,
  size VARCHAR(64) NULL,
  country VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE environments (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  type ENUM('aws','azure','gcp','onprem','other') NOT NULL DEFAULT 'aws',
  region VARCHAR(64) NULL,
  account_ref VARCHAR(128) NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cloud_credentials (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  environment_id CHAR(36) NOT NULL,
  provider VARCHAR(64) NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  secret_encrypted BLOB NULL,
  metadata JSON NULL,
  last_test_at DATETIME NULL,
  last_test_ok TINYINT(1) NULL,
  last_test_error TEXT NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE assets (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  environment_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  asset_type VARCHAR(64) NOT NULL,
  identifier VARCHAR(255) NULL,
  region VARCHAR(64) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE frameworks (
  id CHAR(36) NOT NULL PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  region VARCHAR(64) NULL,
  is_global TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE controls (
  id CHAR(36) NOT NULL PRIMARY KEY,
  framework_id CHAR(36) NOT NULL,
  control_ref VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_controls_fw (framework_id),
  CONSTRAINT fk_controls_fw FOREIGN KEY (framework_id) REFERENCES frameworks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE assessments (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NOT NULL,
  environment_id CHAR(36) NULL,
  name VARCHAR(255) NOT NULL,
  status ENUM('draft','in_progress','review','completed','archived') NOT NULL DEFAULT 'draft',
  scope TEXT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE assessment_frameworks (
  assessment_id CHAR(36) NOT NULL,
  framework_id CHAR(36) NOT NULL,
  tenant_id CHAR(36) NOT NULL,
  PRIMARY KEY (assessment_id, framework_id)
) ENGINE=InnoDB;

CREATE TABLE scan_jobs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  environment_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'queued',
  progress INT NOT NULL DEFAULT 0,
  assets_count INT NOT NULL DEFAULT 0,
  findings_count INT NOT NULL DEFAULT 0,
  error TEXT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE findings (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NULL,
  asset_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  recommendation TEXT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','accepted','false_positive') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE finding_controls (
  finding_id CHAR(36) NOT NULL,
  control_id CHAR(36) NOT NULL,
  PRIMARY KEY (finding_id, control_id)
) ENGINE=InnoDB;

CREATE TABLE evidence (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NULL,
  control_id CHAR(36) NULL,
  finding_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  type ENUM('screenshot','document','log','config','other') NOT NULL DEFAULT 'screenshot',
  file_url TEXT NULL,
  uploaded_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE remediation_tasks (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  finding_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','in_progress','blocked','done','cancelled') NOT NULL DEFAULT 'todo',
  owner CHAR(36) NULL,
  due_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE integrations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  kind VARCHAR(64) NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  base_url VARCHAR(512) NOT NULL,
  project_key VARCHAR(128) NULL,
  config JSON NULL,
  secret_encrypted BLOB NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE integration_links (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  integration_id CHAR(36) NOT NULL,
  remediation_task_id CHAR(36) NOT NULL,
  external_id VARCHAR(128) NOT NULL,
  external_url TEXT NULL,
  external_status VARCHAR(64) NULL,
  last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ai_insights (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  kind VARCHAR(64) NOT NULL,
  subject_type VARCHAR(64) NULL,
  subject_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  model VARCHAR(128) NULL,
  metadata JSON NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ai_summaries (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NULL,
  type ENUM('executive','technical','remediation','board') NOT NULL DEFAULT 'executive',
  title VARCHAR(255) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  generated_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE documents (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(64) NOT NULL DEFAULT 'general',
  tags JSON NULL,
  storage_path VARCHAR(512) NOT NULL,
  file_url TEXT NOT NULL,
  mime_type VARCHAR(128) NULL,
  size_bytes BIGINT NULL,
  uploaded_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE evidence_packs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  file_url TEXT NULL,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  submitted_by CHAR(36) NULL,
  submitted_at DATETIME NULL,
  decided_by CHAR(36) NULL,
  decided_at DATETIME NULL,
  decision_note TEXT NULL,
  metadata JSON NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE evidence_pack_reviews (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  pack_id CHAR(36) NOT NULL,
  decision ENUM('approved','rejected','comment') NOT NULL,
  note TEXT NULL,
  reviewer_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE coverage_snapshots (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assessment_id CHAR(36) NOT NULL,
  snapshot_date DATE NOT NULL,
  total_controls INT NOT NULL DEFAULT 0,
  touched_controls INT NOT NULL DEFAULT 0,
  coverage_pct INT NOT NULL DEFAULT 0,
  findings_open INT NOT NULL DEFAULT 0,
  findings_total INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_snap (tenant_id, assessment_id, snapshot_date)
) ENGINE=InnoDB;

-- Policies module
CREATE TABLE policies (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  summary TEXT NULL,
  body MEDIUMTEXT NULL,
  category VARCHAR(64) NOT NULL DEFAULT 'general',
  version VARCHAR(32) NOT NULL DEFAULT '1.0',
  status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
  owner CHAR(36) NULL,
  document_id CHAR(36) NULL,
  review_cadence_days INT NOT NULL DEFAULT 365,
  next_review_at DATE NULL,
  effective_at DATE NULL,
  metadata JSON NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_policies_tenant (tenant_id)
) ENGINE=InnoDB;

CREATE TABLE policy_controls (
  policy_id CHAR(36) NOT NULL,
  control_id CHAR(36) NOT NULL,
  tenant_id CHAR(36) NOT NULL,
  PRIMARY KEY (policy_id, control_id)
) ENGINE=InnoDB;

CREATE TABLE policy_acknowledgements (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  policy_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  version VARCHAR(32) NOT NULL,
  acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ack (policy_id, user_id, version)
) ENGINE=InnoDB;

CREATE TABLE policy_rules (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  policy_id CHAR(36) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  trigger_event ENUM('finding_created','finding_updated','pack_submitted','pack_approved','pack_rejected','review_due','manual') NOT NULL,
  condition_json JSON NULL,
  action_kind ENUM('create_remediation_task','log_activity','require_approval','notify','create_finding') NOT NULL,
  action_params JSON NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rules_tenant_event (tenant_id, trigger_event, enabled)
) ENGINE=InnoDB;

CREATE TABLE policy_events (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  rule_id CHAR(36) NULL,
  policy_id CHAR(36) NULL,
  trigger_event ENUM('finding_created','finding_updated','pack_submitted','pack_approved','pack_rejected','review_due','manual') NOT NULL,
  subject_type VARCHAR(64) NULL,
  subject_id CHAR(36) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'fired',
  result JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_tenant_created (tenant_id, created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
