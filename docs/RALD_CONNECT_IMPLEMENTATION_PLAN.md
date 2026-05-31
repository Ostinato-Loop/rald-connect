# RALD_CONNECT_IMPLEMENTATION_PLAN.md
## RALD Connect v1 — Implementation Plan
**Generated:** 2026-05-31 | **Status:** EXECUTING

---

## Prerequisites (must complete before testing)

| # | Action | Owner | Status |
|---|---|---|---|
| P1 | Run `20260531_recovery_users_table.sql` in Supabase | Human (DB access) | ⏳ PENDING |
| P2 | Verify `POST /auth/register` returns token | QA | ⏳ BLOCKED on P1 |
| P3 | Verify `POST /auth/login` returns token | QA | ⏳ BLOCKED on P1 |

---

## Phase 0 — Discovery ✅ COMPLETE
- RALD_SYSTEM_MAP.md ✅
- RALD_API_INVENTORY.md ✅
- RALD_INFRASTRUCTURE_MAP.md ✅

## Phase 1 — Identity Audit ✅ COMPLETE
- RALD_IDENTITY_AUDIT.md ✅
- `IdentityProviderInterface` ✅
- `RALDIdentityProvider` ✅
- `RaldAuthClient` ✅
- `RaldTokenStore` ✅

## Phase 2 — API Inventory ✅ COMPLETE
- RALD_API_INVENTORY.md ✅

## Phase 3 — SDK Strategy ✅ COMPLETE
- No pre-existing SDK found in org
- PHP adapter classes serve as SDK foundation
- Architecture supports future extraction to standalone `rald-php-sdk` package

## Phase 4 — Infrastructure Audit ✅ COMPLETE
- RALD_INFRASTRUCTURE_MAP.md ✅

## Phase 5 — Architecture Decision Records ✅ COMPLETE
- ADR-001 through ADR-008 ✅

## Phase 6 — Impact Analysis ✅ COMPLETE
- RALD_CONNECT_IMPACT_REPORT.md ✅

## Phase 7 — Build RALD Connect v1 🔄 IN PROGRESS

### Sprint 1 — Core Identity (Week 1)
- [x] `interface-identity-provider.php`
- [x] `class-rald-auth-client.php`
- [x] `class-rald-identity-provider.php`
- [x] `class-rald-token-store.php`
- [x] `class-rald-auth-hooks.php`
- [x] `class-rald-sso.php`
- [x] `class-rald-user-sync.php`
- [x] REST: `class-rald-rest-auth.php`
- [x] REST: `class-rald-rest-sso.php`
- [x] Templates: login-form, register-form, sso-button

### Sprint 2 — Dashboard + Settings (Week 1-2)
- [x] `class-rald-settings.php`
- [x] `class-rald-admin.php`
- [x] `class-rald-dashboard.php`
- [x] React Admin: Dashboard, Settings, Identity tabs
- [x] REST: `GET /status` health endpoint

### Sprint 3 — RALDTICS Module (Week 2)
- [x] `class-rald-analytics.php`
- [x] Tracking script injection
- [x] REST: `/analytics/beacon`
- [x] Admin analytics tab

### Sprint 4 — Lead Capture (Week 2)
- [x] `class-rald-lead-capture.php`
- [x] Shortcodes: contact, quote, newsletter
- [x] REST: `/leads`
- [x] CRM webhook dispatch

### Sprint 5 — AI SEO (Week 3)
- [x] `class-rald-ai-seo.php`
- [x] Post/page meta box
- [x] REST: `/ai/generate`
- [x] Admin AI SEO tab

### Sprint 6 — Business Profile (Week 3)
- [x] `class-rald-business-profile.php`
- [x] Settings form + sync
- [x] REST: `/business`
- [x] Admin Business Profile tab

### Sprint 7 — Testing (Week 4)
- [ ] Unit tests for PHP classes
- [ ] Integration tests against auth.rald.cloud
- [ ] REST API contract tests
- [ ] Webhook tests
- [ ] WordPress coding standards audit
- [ ] Accessibility audit
- [ ] Performance baseline (< 50ms overhead, dashboard < 2s)

---

## Rollback Plan

| Scenario | Rollback Action |
|---|---|
| Plugin breaks site | Deactivate plugin — all WP functionality restored |
| Auth loop | `rald_token` clear on next login attempt |
| DB corruption | None possible — plugin uses no custom tables |
| RALD Cloud outage | Plugin gracefully degrades — WP core unaffected |

---

## Environment Matrix

| Environment | URL | Supabase | Worker |
|---|---|---|---|
| Production | profiles.rald.cloud | onxdcikfttdmnhofsuwo | rald-auth (prod) |
| Test | local WordPress | Test DB | rald-auth (local) |

---

## Definition of Done

- [ ] All 7 modules functional
- [ ] Zero PHP errors or warnings
- [ ] All API calls proxied through RALD Cloud (no direct DB access)
- [ ] Recovery migration run in Supabase
- [ ] Dashboard shows green for all services
- [ ] E2E: register → verify → redirect working on profiles.rald.cloud
- [ ] Plugin installable as `.zip` from GitHub Release
