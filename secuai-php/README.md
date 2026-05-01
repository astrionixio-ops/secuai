# SecuAI — MySQL + PHP backend

A self-hosted port of the SecuAI compliance backend (originally on Supabase/Postgres) to **MySQL 8 + plain PHP 8.1+** with a JSON REST API.

## Stack
- MySQL 8.x (InnoDB, utf8mb4) — works on MariaDB 10.5+ with minor tweaks.
- PHP 8.1+ with PDO (`pdo_mysql`), no Composer required.
- JWT (HS256) auth, bcrypt password hashing.
- Role checks mirror the original Supabase RLS (`admin`, `auditor`, `analyst`, `viewer`).
- Built-in **Policy Engine** that fires on `finding_created`, `finding_updated`, `pack_submitted`, `pack_approved`, `pack_rejected`.

## Layout
```
sql/
  schema.sql      # all tables
  seed.sql        # demo tenant + users + frameworks
api/
  index.php       # router
  config.example.php
  lib/            # Db, Jwt, Auth, Resp, PolicyEngine
  .htaccess       # Apache pretty URLs
uploads/          # local file storage
```

## Install

1. Create the database and import schema + seed:
   ```bash
   mysql -uroot -p -e "CREATE DATABASE secuai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -uroot -p secuai < sql/schema.sql
   mysql -uroot -p secuai < sql/seed.sql
   ```

2. Copy and edit config:
   ```bash
   cp api/config.example.php api/config.php
   # set DB credentials and a long random jwt_secret
   ```

3. Reset demo passwords (the seed hash is a placeholder):
   ```sql
   -- run after install; sets password "Admin123!" for all demo users
   UPDATE users SET password_hash = '$2y$10$' /* generate via php -r */ WHERE email LIKE '%@demo.local';
   ```
   Or just `POST /auth/signup` to create your own.

4. Serve:
   - **PHP built-in (dev):**
     ```bash
     php -S 0.0.0.0:8080 -t api
     ```
   - **Apache:** point a vhost DocumentRoot to `api/` (the included `.htaccess` rewrites everything to `index.php`).
   - **Nginx:** add `try_files $uri $uri/ /index.php?$query_string;`.

## Auth

```bash
# Sign up
curl -X POST http://localhost:8080/auth/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"Secret123!","display_name":"Me"}'

# Login
curl -X POST http://localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"Secret123!"}'
# -> {"token":"...","user":{...}}
```

All other endpoints require `Authorization: Bearer <token>`.

## Endpoints (summary)

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/signup` | public |
| POST | `/auth/login` | public |
| GET  | `/health` | public |
| GET  | `/me` | current user + tenant memberships |
| POST | `/tenants` | creates tenant + makes caller admin |
| GET  | `/{table}?tenant_id=...` | list tenant rows (members only) |
| POST | `/findings` | analyst+; **fires policy engine** (`finding_created`) |
| PATCH| `/findings/{id}` | analyst+; fires `finding_updated` |
| POST | `/evidence_packs` | analyst+; if `status=submitted` fires `pack_submitted` |
| POST | `/evidence_packs/{id}/decision` | auditor+; body `{"decision":"approved\|rejected","note":"..."}` -> fires `pack_approved/rejected` |
| POST | `/policies` | analyst+ |
| POST | `/policy_rules` | analyst+; body example below |
| PATCH| `/policy_rules/{id}` | toggle `enabled` |
| POST | `/policy_acknowledgements` | any tenant member |
| POST | `/upload` | multipart `file=@...&tenant_id=...` |
| POST | `/assessments`, `/environments`, `/organizations`, `/documents`, `/remediation_tasks` | analyst+ generic create |

### Example: create a policy rule
```json
POST /policy_rules
{
  "tenant_id": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
  "name": "High findings auto-task",
  "trigger_event": "finding_created",
  "condition": { "severity_in": ["high","critical"] },
  "action_kind": "create_remediation_task",
  "action_params": { "title": "Remediate: {{title}}", "due_in_days": 14 }
}
```
Then `POST /findings` with `severity:"high"` — a row will appear in `remediation_tasks`, plus an entry in `policy_events` (`status='fired'`).

## Differences vs the Supabase version
- **No row-level security at the DB layer.** Tenant + role checks are enforced in PHP (`Auth::requireMember` / `Auth::requireRole`). Do **not** expose the DB directly to clients.
- **Policy engine runs synchronously** inside the same PHP request that mutates `findings`/`evidence_packs` (no `pg_net`/edge functions). Errors are swallowed into `policy_events`.
- File storage is local (`/uploads`). Swap `Resp` of `/upload` for S3/GCS if needed.
- AI endpoints (`ai_insights`, `ai_summaries`) are storage-only — wire them to your own LLM provider.

## Demo accounts (after running seed.sql + setting passwords)
| Email | Role | Tenant |
|---|---|---|
| admin@demo.local | admin | Demo Workspace |
| analyst@demo.local | analyst | Demo Workspace |
| auditor@demo.local | auditor | Demo Workspace |
