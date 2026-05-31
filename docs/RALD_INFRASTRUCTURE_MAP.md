# RALD Infrastructure Map
**Phase 2 — Pre-Build Discovery**
**Date:** 2026-05-31 | **Owner:** LILCKY STUDIO LIMITED

---

## Cloudflare Configuration

**Account:** Ideamack@gmail.com
**Account ID:** `d5a1cd03b76f467430034af64a7062fd`

### Workers

| Worker Name | wrangler name | Domain | Route | Repo |
|-------------|--------------|--------|-------|------|
| `rald-auth` | `rald-auth` | `auth.rald.cloud` | `auth.rald.cloud/*` | rald-auth-core |
| `rald-api` | `rald-api` | `api.rald.cloud` | `api.rald.cloud/*` | rald (main) |
| `raldtics` | (check repo) | `raldtics.cloud` | `raldtics.cloud/*` | raldtics-core |

### Pages Projects

| CF Pages Project | Domain | Source Repo | Build Command | Output Dir |
|-----------------|--------|-------------|--------------|------------|
| `rald-app` | `profiles.rald.cloud` | `rald-auth-ui` | `pnpm build` | `dist` |
| `rald-control-center` | `admin.rald.cloud` | `rald` (main) | `pnpm build` | `dist/public` |
| `rald-marketing` | `rald.cloud` | `rald` (main) | `pnpm build` | `dist/public` |

### DNS (via cloudflare-dns.sh)

```
auth.rald.cloud          → CNAME rald-auth.ideamack.workers.dev (proxied)
profiles.rald.cloud      → CNAME rald-app.pages.dev (proxied)
api.rald.cloud           → CNAME rald-api.ideamack.workers.dev (proxied)
admin.rald.cloud         → CNAME rald-control-center.pages.dev (proxied)
rald.cloud               → A/CNAME rald-marketing.pages.dev (proxied)
www.rald.cloud           → CNAME rald.cloud (proxied)
```

---

## Supabase

**Project ID:** `onxdcikfttdmnhofsuwo`
**Dashboard:** https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo
**SQL Editor:** https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo/sql/new

### Connection Details
- REST API: `https://onxdcikfttdmnhofsuwo.supabase.co/rest/v1/`
- All Worker access via Service Role Key (bypasses RLS)
- Direct anon access disabled (RLS blocks all anon queries)

### Shared Database Warning ⚠️
This Supabase project is shared between RALD and at least one other project (Manilla/SquadCo).
**NEVER** run `DROP TABLE` or destructive migrations without explicitly scoping to RALD-owned tables.
RALD-owned tables are prefixed contextually: `users`, `sessions`, `user_devices`, `product_access`, `otp_codes`, `referral_codes`, `referrals`, `waitlist`, `services`, `deployments`, `products`, `credentials`.

---

## GitHub Organisation

**Org:** `Ostinato-Loop`
**Repos of Interest (auth/platform):**

| Repo | Purpose |
|------|---------|
| `rald` | Main monorepo: API worker, admin UI, marketing |
| `rald-auth-core` | Auth Worker (Hono) — auth.rald.cloud |
| `rald-auth-ui` | Auth frontend — profiles.rald.cloud |
| `rald-auth-sdk` | TypeScript/JS SDK for frontend apps |
| `rald-sdk-react` | React hooks for RALD Auth |
| `rald-shared-sdk` | Shared types/interfaces |
| `rald-api-core` | (Reference) |
| `raldtics-core` | Analytics ingestion Worker |
| `loop-crm` | CRM sync service |
| `rald-connect` | ← **This plugin** |

---

## CI/CD Pipeline

```
Ostinato-Loop GitHub Org
    │
    ├── rald-auth-core (push to main)
    │       ├── ci.yml    → typecheck + build
    │       └── deploy.yml → wrangler deploy rald-auth Worker
    │
    ├── rald-auth-ui (push to main)
    │       ├── ci.yml    → typecheck + build
    │       └── deploy.yml → wrangler pages deploy → rald-app Pages
    │
    └── rald (main) (push to main)
            ├── ci.yml    → typecheck + build all packages
            └── deploy.yml → parallel: api-worker, app, control-center, marketing
```

**Required GitHub Secrets (org-level or per-repo):**

| Secret | Used By |
|--------|---------|
| `CLOUDFLARE_API_TOKEN` | All deploy workflows |
| `CLOUDFLARE_ACCOUNT_ID` | All deploy workflows |
| `SUPABASE_URL` | rald-auth-core, rald deploy secrets |
| `SUPABASE_SERVICE_ROLE_KEY` | rald-auth-core, rald deploy secrets |
| `RALD_JWT_SECRET` | rald-auth-core deploy secrets |
| `TERMII_API_KEY` | rald-auth-core deploy secrets |
| `RESEND_API_KEY` | rald-auth-core, rald deploy secrets |
| `CLERK_SECRET_KEY` | rald-auth-core deploy secrets |

---

## rald-connect Plugin Infrastructure Requirements

The plugin runs entirely within WordPress (no Cloudflare infrastructure needed). It makes outbound HTTPS calls to:

| Target | URL | Protocol |
|--------|-----|---------|
| RALD Auth API | `https://auth.rald.cloud` | HTTPS REST |
| RALD Main API | `https://api.rald.cloud` | HTTPS REST |
| RALDtics (events) | `https://raldtics.cloud/ingest` | HTTPS POST |

**WordPress Server Requirements:**
- PHP 7.4+ (8.x recommended)
- `allow_url_fopen` or cURL enabled for outbound HTTPS
- OpenSSL extension for JWT verification
- WordPress 5.8+ (REST API, block editor hooks)

**No Additional Infrastructure:** No new Cloudflare Workers, Pages, or Supabase tables are needed for `rald-connect` v1.

---

## Security Boundaries

```
WordPress Admin
    │  wp_options: rald_connect_settings (encrypted API keys)
    │
    ▼
rald-connect Plugin (PHP)
    │  wp_remote_post() with TLS
    ▼
auth.rald.cloud (CF Worker)
    │  SUPABASE_SERVICE_ROLE_KEY (Worker secret, never in plugin)
    ▼
Supabase (Postgres)
```

**Key principle:** The plugin NEVER holds the Supabase service role key. It only holds a WordPress-site-specific API credential issued by `api.rald.cloud`. The credential is stored encrypted in `wp_options` using WordPress's `SECURE_AUTH_KEY`.
