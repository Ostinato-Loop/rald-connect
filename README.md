# RALD Connect

WordPress plugin that connects any WordPress site to the [RALD Identity platform](https://rald.cloud).

[![Build & Release](https://github.com/Ostinato-Loop/rald-connect/actions/workflows/release.yml/badge.svg)](https://github.com/Ostinato-Loop/rald-connect/actions/workflows/release.yml)

---

## What it does

- **Replaces WordPress login/registration** with RALD Identity (`auth.rald.cloud`)
- **SSO bridge** — `[rald_sso_button app_id="rald-app"]` sends logged-in users to any RALD app with a session token exchange
- **User sync** — RALD users appear as WordPress users (shadow accounts, RALD is source of truth)
- **REST API** — `/wp-json/rald-connect/v1/auth/{login,register,logout,me}`
- **React admin dashboard** — configure endpoints, test connection, map roles

---

## Requirements

| Requirement | Version |
|------------|---------|
| WordPress  | 5.8+ |
| PHP        | 7.4+ |
| auth.rald.cloud | Reachable from WP server |

---

## Installation

```bash
# From GitHub release (zip):
WordPress Admin → Plugins → Add New → Upload Plugin → rald-connect-v*.zip

# From source:
npm install && npm run build
zip -r rald-connect.zip rald-connect/
```

Configure at **Settings → RALD Connect**.

---

## Usage

### Login form (shortcode)
```
[rald_login_form]
```

### Registration form (shortcode)
```
[rald_register_form]
```

### SSO button
```
[rald_sso_button app_id="rald-app" label="Open RALD Dashboard"]
```

### Programmatic SSO redirect
Link to `/?rald_sso_to=rald-app&redirect_to=https://app.rald.cloud/dashboard`

---

## REST API

| Method | Endpoint | Auth |
|--------|---------|------|
| POST | `/wp-json/rald-connect/v1/auth/login` | — |
| POST | `/wp-json/rald-connect/v1/auth/register` | — |
| POST | `/wp-json/rald-connect/v1/auth/logout` | Cookie |
| GET  | `/wp-json/rald-connect/v1/auth/me` | Cookie |
| POST | `/wp-json/rald-connect/v1/sso/exchange` | Cookie |
| GET  | `/wp-json/rald-connect/v1/admin/settings` | Admin |
| POST | `/wp-json/rald-connect/v1/admin/settings` | Admin |

---

## Pre-Build Discovery Docs

See [`docs/`](docs/) for full Phase 0–5 discovery documentation:

| Doc | Phase | Content |
|-----|-------|---------|
| [RALD_SYSTEM_MAP.md](docs/RALD_SYSTEM_MAP.md) | 0 | Full ecosystem architecture |
| [RALD_API_INVENTORY.md](docs/RALD_API_INVENTORY.md) | 1 | Every auth.rald.cloud endpoint |
| [RALD_INFRASTRUCTURE_MAP.md](docs/RALD_INFRASTRUCTURE_MAP.md) | 2 | Cloudflare, Supabase, GitHub Actions |
| [RALD_CONNECT_IMPACT_REPORT.md](docs/RALD_CONNECT_IMPACT_REPORT.md) | 3–5 | Data flows, risks, build plan |
| [RECOVERY_MIGRATION.md](docs/RECOVERY_MIGRATION.md) | — | Fix for dropped users table |

---

## profiles.rald.cloud Recovery

The `users` table in Supabase was dropped by a previous agent session. See [`docs/RECOVERY_MIGRATION.md`](docs/RECOVERY_MIGRATION.md) for the step-by-step fix.

**Quick fix:**
1. Open: https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo/sql/new
2. Paste: `rald-auth-core/supabase/migrations/20260531_recovery_users_table.sql`
3. Click **Run**

---

## Architecture

```
WordPress Site
    │
    ├── RALD Connect Plugin (PHP)
    │       │
    │       ├── RALD_Auth_Client ──────▶ auth.rald.cloud (Hono Worker)
    │       ├── RALD_Token_Store         ├── /auth/login
    │       ├── RALD_User_Sync           ├── /auth/register
    │       ├── RALD_Auth_Hooks          ├── /auth/me
    │       ├── RALD_SSO                 └── /sso/exchange
    │       └── REST API
    │
    └── React Admin Dashboard (Vite + TypeScript)
```

**Security:** Tokens encrypted with `SECURE_AUTH_KEY` before storage. The Supabase service role key never leaves `auth.rald.cloud`.

---

## License

GPL-2.0+ — LILCKY STUDIO LIMITED
