# RALD System Map
**Phase 0 — Pre-Build Discovery**
**Date:** 2026-05-31 | **Owner:** LILCKY STUDIO LIMITED

---

## 1. Ecosystem Overview

RALD is a closed identity and API platform built on Cloudflare's edge network with Supabase as the shared datastore. Every service is deployed from the `Ostinato-Loop` GitHub organisation. GitHub Actions provides CI/CD to Cloudflare Workers (compute) and Cloudflare Pages (static frontends).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        RALD ECOSYSTEM                                        │
│                                                                             │
│  ┌──────────────────────┐    ┌──────────────────────┐                      │
│  │  profiles.rald.cloud  │    │  auth.rald.cloud      │                     │
│  │  (Cloudflare Pages)   │───▶│  (Cloudflare Worker)  │                     │
│  │  Repo: rald-auth-ui   │    │  Repo: rald-auth-core │                     │
│  └──────────────────────┘    └──────────┬───────────┘                      │
│                                          │                                   │
│  ┌──────────────────────┐    ┌───────────▼──────────┐                      │
│  │  api.rald.cloud       │    │  Supabase             │                     │
│  │  (Cloudflare Worker)  │───▶│  Project ID:          │                     │
│  │  Repo: rald (main)    │    │  onxdcikfttdmnhofsuwo │                     │
│  └──────────────────────┘    └──────────────────────┘                      │
│                                                                             │
│  ┌──────────────────────┐    ┌──────────────────────┐                      │
│  │  admin.rald.cloud     │    │  rald.cloud (mktg)   │                     │
│  │  (Cloudflare Pages)   │    │  (Cloudflare Pages)   │                     │
│  │  Repo: rald (main)    │    │  Repo: rald (main)    │                     │
│  └──────────────────────┘    └──────────────────────┘                      │
│                                                                             │
│  ┌──────────────────────┐    ┌──────────────────────┐                      │
│  │  RALDtics Analytics   │    │  loop-crm             │                     │
│  │  Repo: raldtics-core  │    │  Repo: loop-crm       │                     │
│  └──────────────────────┘    └──────────────────────┘                      │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Service Inventory

| Service | Domain | Kind | Repo | Runtime | Status |
|---------|--------|------|------|---------|--------|
| Auth API | `auth.rald.cloud` | Worker | `rald-auth-core` | Cloudflare Worker (Hono) | ✅ Live |
| Profiles UI | `profiles.rald.cloud` | Pages | `rald-auth-ui` | Cloudflare Pages (React+Vite) | ⚠️ DB broken |
| Main API | `api.rald.cloud` | Worker | `rald` (main) | Cloudflare Worker (Hono) | ✅ Live |
| Admin Dashboard | `admin.rald.cloud` | Pages | `rald` (main) | Cloudflare Pages | ✅ Live |
| Marketing | `rald.cloud` | Pages | `rald` (main) | Cloudflare Pages | ✅ Live |
| Analytics | `raldtics.cloud` | Worker | `raldtics-core` | Cloudflare Worker | ✅ Live |
| CRM | Internal | Worker | `loop-crm` | Cloudflare Worker | ✅ Live |

---

## 3. Identity Flow (Current)

```
User visits profiles.rald.cloud
        │
        ▼
rald-auth-ui (React SPA, Cloudflare Pages)
        │  POST /auth/login  or  POST /auth/register
        ▼
auth.rald.cloud  (Cloudflare Worker, Hono)
   rald-auth-core/src/routes/auth.ts
        │
        ├── bcrypt verify password
        ├── SELECT FROM users (Supabase)
        ├── Issue JWT (RALD_JWT_SECRET, 24h)
        └── Return { token, user }
                │
                ▼
        rald-auth-ui stores token in localStorage
        Redirects user to app (via /sso/clerk-exchange or /sso/exchange)
```

### SSO Exchange Flow (App Redirect)
```
App (e.g. app.rald.cloud) → redirects to profiles.rald.cloud?redirect_to=<url>&app_id=<id>
                                        │
                          User logs in / already authenticated
                                        │
                         rald-auth-ui calls POST /sso/exchange
                                        │  Bearer: <rald_token>
                                        ▼
                         auth.rald.cloud creates short-lived session token
                                        │
                         Redirects back to app?rald_token=<session_token>
```

---

## 4. Database Architecture

**Supabase Project:** `onxdcikfttdmnhofsuwo`
**Region:** Determined by Supabase project settings
**Access Pattern:** All writes via service role key from Cloudflare Workers. RLS enabled on all tables. No direct anon/client access.

### Table Map (Shared across ALL RALD services)

| Table | Owner Service | Purpose | FK Status |
|-------|--------------|---------|-----------|
| `users` | rald-auth-core | Platform accounts | ⚠️ DROPPED — needs recovery |
| `sessions` | rald-auth-core | JWT session tracking | ⚠️ FK dropped |
| `user_devices` | rald-auth-core | Device trust | ✅ |
| `product_access` | rald-auth-core | Product provisioning | ✅ |
| `otp_codes` | rald-auth-core | OTP rate-limiting | ✅ |
| `referral_codes` | rald (main) | Referral programme | ⚠️ FK dropped |
| `referrals` | rald (main) | Referral tracking | ⚠️ FK dropped |
| `waitlist` | rald (main) | Pre-launch waitlist | ⚠️ FK dropped |
| `services` | rald (main) | Service registry | ✅ |
| `deployments` | rald (main) | Deploy tracking | ✅ |
| `products` | rald (main) | Product catalogue | ✅ |
| `credentials` | rald (main) | API key store | ✅ |

---

## 5. Auth SDK Landscape

| SDK | Repo | Language | Status | Use |
|-----|------|----------|--------|-----|
| rald-auth-sdk | `rald-auth-sdk` | TypeScript/JS | ✅ Published | Frontend apps |
| rald-sdk-react | `rald-sdk-react` | React hooks | ✅ Published | React apps |
| rald-shared-sdk | `rald-shared-sdk` | TypeScript | ✅ Published | Shared types |
| rald-connect SDK | `rald-connect` | PHP | 🔨 Building | WordPress |

---

## 6. profiles.rald.cloud — Current Broken State

### Root Cause
A previous agent session dropped the `users` table (7 rows lost) and dropped FK constraints from `referral_codes`, `referrals`, `sessions`, and `waitlist`. The `users` table is foundational — every auth endpoint depends on it.

### Recovery
See `docs/RECOVERY_MIGRATION.md` — run `supabase/migrations/20260531_recovery_users_table.sql` in the Supabase SQL Editor.

### What Was NOT Broken
- The Cloudflare Worker (`auth.rald.cloud`) is fully deployed and correct
- `/sso/clerk-exchange` EXISTS and works when a valid token is present
- `/sso/exchange`, `/sso/verify`, `/auth/me` all exist in the correct routes
- The `rald-auth-ui` frontend bundle is correct
- All other tables retain their data

---

## 7. Deployment Pipeline

```
GitHub push → main
    │
    ├── .github/workflows/ci.yml     (typecheck + build)
    └── .github/workflows/deploy.yml (parallel CF deploys)
            │
            ├── Workers: wrangler deploy
            └── Pages:   wrangler pages deploy
```

**Cloudflare Account:** `d5a1cd03b76f467430034af64a7062fd` (Ideamack@gmail.com)

---

## 8. RALD Connect Plugin — Integration Points

The WordPress plugin (`rald-connect`) integrates with:

1. **auth.rald.cloud** — identity verification, token exchange
2. **api.rald.cloud** — product provisioning, data sync
3. **RALDtics** — analytics event ingestion
4. **loop-crm** — contact/lead sync (optional)

Plugin does NOT:
- Store passwords or tokens in WordPress DB (tokens stored in WordPress options encrypted, or passed-through only)
- Expose Clerk internals
- Bypass RALD Identity (no OAuth shortcuts)
