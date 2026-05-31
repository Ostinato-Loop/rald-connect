# RALD Connect — Impact Report
**Phase 3–5 Pre-Build Discovery**
**Date:** 2026-05-31 | **Owner:** LILCKY STUDIO LIMITED

---

## Executive Summary

`rald-connect` is a WordPress plugin that brings the RALD identity system into any WordPress site. It replaces WordPress's native login/registration flow with RALD Identity, enables SSO to other RALD apps, and optionally syncs analytics events to RALDtics.

**Impact scope:** Zero changes required to `auth.rald.cloud`, `api.rald.cloud`, or Supabase. The plugin is purely an outbound HTTP client — read-only integration pattern.

---

## Phase 3 — Data Flow Analysis

### Login Flow

```
WordPress Login Form (custom)
    │  POST wp-login.php or REST /rald-connect/v1/auth/login
    ▼
RALD Connect PHP SDK
    │  POST https://auth.rald.cloud/auth/login
    ▼
Returns { token, user }
    │
    ├── Store token: wp_set_auth_cookie() equivalent
    │   encrypted in transient or cookie
    │
    ├── Map RALD user → WP_User
    │   rald_id → user_login
    │   email   → user_email
    │   role    → wp_capabilities (mapped via settings)
    │
    └── wp_set_current_user() + do_action('wp_login')
```

### Registration Flow

```
WordPress Registration Form (custom)
    │
    ▼
RALD Connect
    │  POST https://auth.rald.cloud/auth/register
    ▼
RALD creates account, returns { token, user }
    │
    ├── wp_insert_user() creates WordPress shadow user
    │   user_login = rald_id (e.g. RALD-ABC123)
    │   user_pass  = wp_generate_password() [random, never used]
    │   meta: _rald_token, _rald_id, _rald_verified
    │
    └── Auto-login via wp_set_auth_cookie()
```

### SSO Redirect Flow

```
WordPress user already logged in
    │  User clicks "Open [RALD App]"
    ▼
RALD Connect calls POST https://auth.rald.cloud/sso/exchange
    │  Bearer: stored rald_token
    │  Body: { app_id: "target-app", redirect_to: "https://app.rald.cloud" }
    ▼
Returns { token: "short-lived", redirect_url: "https://app.rald.cloud?rald_token=..." }
    │
    └── wp_redirect( redirect_url )
```

### Token Refresh / Verification

```
On every WordPress page load (authenticated user):
    │
    ├── Check: is _rald_token in user_meta present?
    ├── Check: is token expiry within 1 hour? 
    │   Yes → refresh via GET /auth/me (validates token)
    │
    └── On 401 → clear WP session → redirect to login
```

---

## Phase 4 — Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| auth.rald.cloud downtime breaks WP login | Low | High | Configurable fallback: allow existing WP users to log in natively if RALD unreachable |
| Token storage in user_meta (plaintext) | Medium | High | Encrypt with `SECURE_AUTH_KEY` before storing; never log tokens |
| Shared Supabase DB corruption (repeat of dropped table) | Low | Critical | Plugin makes NO direct Supabase calls; fully insulated |
| WordPress user accounts accumulate | Low | Low | Shadow WP users are lean (only required fields, no post data) |
| RALD API rate limits | Low | Low | Plugin caches token validation results for 5 minutes per user |
| WP auto-update breaks plugin | Low | Medium | Semantic versioning, WordPress.org plugin guidelines followed |
| Cross-site token leakage | Low | High | Tokens stored server-side in user_meta, never exposed to JS unless explicitly required for SSO |

---

## Phase 5 — Build Plan

### MVP Scope (v1.0)

1. **Core Auth Module** (`includes/identity/`)
   - `class-rald-identity-provider.php` — implements `IdentityProviderInterface`
   - `class-rald-auth-client.php` — PHP wrapper for auth.rald.cloud API
   - `class-rald-token-store.php` — encrypted token persistence in user_meta

2. **WordPress Integration Module** (`includes/modules/`)
   - `class-rald-auth-hooks.php` — hooks into `authenticate`, `wp_login`, `wp_logout`
   - `class-rald-user-sync.php` — RALD user ↔ WP_User sync
   - `class-rald-sso.php` — SSO exchange handler

3. **REST API** (`includes/rest/`)
   - `class-rald-rest-auth.php` — `/wp-json/rald-connect/v1/auth/login|register|logout|me`
   - `class-rald-rest-sso.php` — `/wp-json/rald-connect/v1/sso/exchange`

4. **Admin Settings** (`admin/`)
   - React dashboard: connection settings, user mapping, analytics toggle
   - `class-rald-admin.php` — settings page registration

5. **Templates** (`templates/`)
   - `login-form.php` — custom RALD login form
   - `register-form.php` — custom RALD registration form
   - `sso-button.php` — "Sign in with RALD" button component

### Out of Scope for v1

- Multisite (WordPress Network) support
- WooCommerce customer sync
- Direct Supabase access (always via auth.rald.cloud)
- Offline/cached auth
- Mobile app SSO (handled by rald-sdk-react natively)

---

## Compatibility Matrix

| Environment | Status |
|------------|--------|
| WordPress 5.8+ | ✅ Required |
| WordPress 6.x | ✅ Tested target |
| PHP 7.4 | ✅ Minimum |
| PHP 8.0 / 8.1 / 8.2 | ✅ Supported |
| WooCommerce 7+ | 🔜 v1.1 |
| BuddyPress | 🔜 v1.2 |
| Multisite | 🔜 v2.0 |
| Classic Editor | ✅ Settings page only |
| Block Editor | ✅ Settings page React app |

---

## Plugin File Structure

```
rald-connect/
├── rald-connect.php              Main plugin file, bootstrap
├── includes/
│   ├── class-rald-connect.php    Core plugin class
│   ├── identity/
│   │   ├── interface-identity-provider.php
│   │   ├── class-rald-identity-provider.php
│   │   ├── class-rald-auth-client.php
│   │   └── class-rald-token-store.php
│   ├── modules/
│   │   ├── class-rald-auth-hooks.php
│   │   ├── class-rald-user-sync.php
│   │   └── class-rald-sso.php
│   └── rest/
│       ├── class-rald-rest-auth.php
│       └── class-rald-rest-sso.php
├── admin/
│   ├── class-rald-admin.php
│   ├── js/src/                   React admin app (built to admin/js/dist/)
│   └── css/rald-admin.css
├── templates/
│   ├── login-form.php
│   ├── register-form.php
│   └── sso-button.php
├── assets/
│   └── images/rald-logo.svg
├── languages/
│   └── rald-connect.pot
├── docs/                         Pre-build discovery docs
├── .github/workflows/
│   └── release.yml               Build + tag release on push to main
├── package.json                  Admin JS build (Vite + React)
├── composer.json                 PHP dependencies (firebase/php-jwt)
└── README.md
```

---

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| PHP JWT verification using `firebase/php-jwt` | Industry standard, well-maintained, matches RALD JWT spec |
| Shadow WP users (not native WP auth) | RALD is source of truth; WP user is a proxy |
| Token stored in user_meta (encrypted) | Server-side storage, not cookies, avoids XSS surface |
| REST API for login/register | Compatible with headless WP, Gutenberg blocks, AJAX |
| React admin dashboard | Matches RALD ecosystem UX; built with Vite, bundled |
| `NOT VALID` FK constraints on recovery | Preserves existing orphaned rows, enforces new inserts |
