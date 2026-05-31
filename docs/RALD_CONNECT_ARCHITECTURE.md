# RALD_CONNECT_ARCHITECTURE.md
## RALD Connect v1 — Architecture Document
**Generated:** 2026-05-31  
**Status:** APPROVED FOR IMPLEMENTATION

---

## 1. System Overview

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Site                       │
│  ┌────────────────────────────────────────────────────┐ │
│  │              RALD Connect Plugin                   │ │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────────────┐   │ │
│  │  │Dashboard │ │Identity  │ │   RALDTICS       │   │ │
│  │  │          │ │Adapter   │ │   Analytics      │   │ │
│  │  └──────────┘ └──────────┘ └──────────────────┘   │ │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────────────┐   │ │
│  │  │Lead      │ │AI SEO    │ │Business Profile  │   │ │
│  │  │Capture   │ │          │ │                  │   │ │
│  │  └──────────┘ └──────────┘ └──────────────────┘   │ │
│  │  ┌───────────────────────────────────────────────┐ │ │
│  │  │               Settings                        │ │ │
│  │  └───────────────────────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────┬────────────────────────────────────┘
                     │ HTTPS / REST API
                     ▼
        ┌────────────────────────┐
        │     RALD Cloud         │
        │                        │
        │  auth.rald.cloud       │ ◄── Identity
        │  api.rald.cloud        │ ◄── CRM, Analytics, AI
        │  (RALDTICS endpoint)   │ ◄── Analytics
        └────────────────────────┘
                     │
                     ▼
        ┌────────────────────────┐
        │   Supabase (DB)        │
        │   Cloudflare Workers   │
        │   Cloudflare KV        │
        └────────────────────────┘
```

---

## 2. Module Architecture

### 2.1 Plugin Bootstrap (`rald-connect.php`)

```
rald-connect.php
  └── RaldConnect (singleton)
        ├── loads Settings
        ├── loads Identity Adapter
        ├── conditionally loads each module
        └── registers REST API routes
```

### 2.2 Identity Module

```
IdentityProviderInterface
  └── RALDIdentityProvider
        ├── RaldAuthClient (HTTP client → auth.rald.cloud)
        └── RaldTokenStore (AES-256-CBC in user_meta)
```

**Flow — Login:**
1. WP login form → `RaldAuthHooks::authenticate()`
2. `RALDIdentityProvider::login(email, password)`
3. `RaldAuthClient::post('/auth/login')`
4. Token stored encrypted in user_meta
5. WP shadow user created/updated
6. Session established

**Flow — SSO Redirect:**
1. External app redirects → `profiles.rald.cloud?redirect_to=URL&app_id=ID`
2. `App.tsx` captures params → `sessionStorage`
3. User authenticates
4. `api.ssoExchange(appId)` → short-lived token
5. Redirect to `URL?rald_token=TOKEN&app_id=ID`

### 2.3 RALDTICS Module

```
RaldAnalytics
  ├── Tracking Script Injection (wp_enqueue_scripts)
  ├── REST Endpoint: /wp-json/rald-connect/v1/analytics/beacon
  └── Admin Dashboard Widget
```

All analytics events proxied to RALD analytics infrastructure. No local tracking storage.

### 2.4 Lead Capture Module

```
RaldLeadCapture
  ├── Shortcodes: [rald_contact_form], [rald_quote_form], [rald_newsletter_form]
  ├── REST Endpoint: POST /wp-json/rald-connect/v1/leads
  └── Webhook Dispatch → RALD CRM
```

### 2.5 AI SEO Module

```
RaldAiSeo
  ├── Meta Box: post/page editor
  ├── REST Endpoint: POST /wp-json/rald-connect/v1/ai/generate
  └── Proxied → api.rald.cloud/ai/seo
```

### 2.6 Business Profile Module

```
RaldBusinessProfile
  ├── Settings Page
  ├── REST Endpoint: POST /wp-json/rald-connect/v1/business
  └── Webhook Dispatch → RALD Cloud
```

---

## 3. REST API Surface

All routes under: `/wp-json/rald-connect/v1/`

| Route | Method | Auth | Module |
|---|---|---|---|
| `/auth/login` | POST | None | Identity |
| `/auth/register` | POST | None | Identity |
| `/auth/logout` | POST | Cookie/Nonce | Identity |
| `/auth/me` | GET | Cookie/Nonce | Identity |
| `/sso/exchange` | POST | Cookie/Nonce | Identity |
| `/analytics/beacon` | POST | None | RALDTICS |
| `/leads` | POST | Nonce | Lead Capture |
| `/ai/generate` | POST | Nonce | AI SEO |
| `/business` | POST | Nonce+Admin | Business Profile |
| `/status` | GET | None | Dashboard |

---

## 4. Security Architecture

| Layer | Implementation |
|---|---|
| Transport | HTTPS only; reject HTTP |
| Authentication | WP nonces (forms), WP cookies (session), Bearer (RALD API) |
| Authorization | `current_user_can()` checks on all privileged routes |
| Input Validation | `sanitize_*()` and `wp_kses()` on all inputs |
| Output Escaping | `esc_html()`, `esc_attr()`, `esc_url()` everywhere |
| CSRF | WP nonces on all state-changing actions |
| SQL Injection | `$wpdb->prepare()` exclusively |
| Token Storage | AES-256-CBC encrypted in user_meta |
| Secret Rotation | Re-encryption hook on `SECURE_AUTH_KEY` change |
| Rate Limiting | Enforced at RALD Cloud layer; plugin respects 429 responses |

---

## 5. Performance Architecture

| Requirement | Implementation |
|---|---|
| Dashboard < 2s | REST status endpoint cached 30s in WP Object Cache |
| Plugin overhead < 50ms | Modules loaded conditionally; no init on non-admin pages |
| Background sync | WP Cron for profile and session sync |
| Redis compatible | All caching via `wp_cache_*()` — Redis Object Cache drop-in supported |
| Lazy loading | Admin JS loaded only on RALD Connect admin pages |
| Minimal DB footprint | 3 custom options max; all data lives in RALD Cloud |

---

## 6. Multi-Platform Future

```
RALD SDK (core)
  ├── Auth Module
  ├── Analytics Module
  ├── CRM Module
  ├── AI Module
  └── Webhooks Module

Platform Adapters (thin):
  ├── rald-connect (WordPress)     ← current
  ├── rald-connect-wix             ← future
  ├── rald-connect-shopify         ← future
  ├── rald-connect-webflow         ← future
  └── rald-connect-squarespace     ← future
```

Business logic stays in RALD Cloud. Platform adapters contain only:
- Configuration UI
- Hook/event bindings
- Display rendering

---

## 7. Deployment Architecture

| Artifact | Host | CI |
|---|---|---|
| Plugin `.zip` | GitHub Releases | GitHub Actions |
| Admin React bundle | Built into plugin via Vite | `pnpm build` in CI |
| RALD Cloud services | Cloudflare Workers | Wrangler deploy (separate repo) |

Plugin auto-update mechanism uses GitHub Releases API (no WP.org dependency required for initial rollout).
