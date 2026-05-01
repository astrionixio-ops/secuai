-- Demo seed: 1 tenant, 1 admin user (password: Admin123!), 1 framework + controls
-- password_hash for "Admin123!" via PHP password_hash(PASSWORD_BCRYPT). Replace after first install if desired.

INSERT INTO users (id, email, password_hash, display_name, email_confirmed) VALUES
('11111111-1111-1111-1111-111111111111','admin@demo.local','$2y$10$wH8q6vJ8cQpZ6E3a0e5lQuQq2YJ7nP0uYxJ5k9b3Ckt5xE5b4Hq8a','Demo Admin',1),
('22222222-2222-2222-2222-222222222222','analyst@demo.local','$2y$10$wH8q6vJ8cQpZ6E3a0e5lQuQq2YJ7nP0uYxJ5k9b3Ckt5xE5b4Hq8a','Demo Analyst',1),
('33333333-3333-3333-3333-333333333333','auditor@demo.local','$2y$10$wH8q6vJ8cQpZ6E3a0e5lQuQq2YJ7nP0uYxJ5k9b3Ckt5xE5b4Hq8a','Demo Auditor',1);

INSERT INTO profiles (id, user_id, display_name) VALUES
(UUID(),'11111111-1111-1111-1111-111111111111','Demo Admin'),
(UUID(),'22222222-2222-2222-2222-222222222222','Demo Analyst'),
(UUID(),'33333333-3333-3333-3333-333333333333','Demo Auditor');

INSERT INTO tenants (id, name, slug) VALUES
('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','Demo Workspace','demo');

INSERT INTO branding (id, tenant_id, product_name) VALUES
(UUID(),'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','SecuAI Enterprise');

INSERT INTO tenant_members (id, tenant_id, user_id, role) VALUES
(UUID(),'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','11111111-1111-1111-1111-111111111111','admin'),
(UUID(),'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','22222222-2222-2222-2222-222222222222','analyst'),
(UUID(),'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','33333333-3333-3333-3333-333333333333','auditor');

INSERT INTO frameworks (id, code, name, description, region, is_global) VALUES
('ffffffff-1111-1111-1111-000000000001','ISO27001','ISO/IEC 27001:2022','Information security management','GLOBAL',1),
('ffffffff-1111-1111-1111-000000000002','SOC2','SOC 2 Trust Services','Security, Availability, Confidentiality','GLOBAL',1);

INSERT INTO controls (id, framework_id, control_ref, title, category) VALUES
(UUID(),'ffffffff-1111-1111-1111-000000000001','A.5.1','Information security policies','Organizational'),
(UUID(),'ffffffff-1111-1111-1111-000000000001','A.8.1','User endpoint devices','Asset Mgmt'),
(UUID(),'ffffffff-1111-1111-1111-000000000002','CC6.1','Logical and physical access controls','Security');
