# RALD_IDENTITY_AUDIT.md
## Phase 1 — Identity Audit
**Generated:** 2026-05-31  
**Status:** COMPLETE  
**Scope:** All identity endpoints exposed by `auth.rald.cloud` (rald-auth-core)

---

## 1. Identity Source

| Property | Value |
|---|---|
| Provider | RALD Identity (auth.rald.cloud) |
| Worker | `rald-auth` (Cloudflare Worker v1.2.0) |
| Runtime | Hono on Cloudflare Workers |
| Database | Supabase (PostgreSQL) — project `onxdcikfttdmnhofsuwo` |
| Internal Provider | May use Clerk internally for legacy flows |
| Base URL | `https://auth.rald.cloud` |

> **RULE:** RALD Connect must never depend on Clerk SDKs, Clerk URLs, Clerk branding, or Clerk session objects. The plugin communicates exclusively with `auth.rald.cloud`.

---

## 2. Endpoint Audit

### 2.1 Registration

| Property | Value |
|---|---|
| Endpoint | `POST /auth/register` |
| Auth Required | No |
| Request | `{ email, password, name, phone?, raldId?, role? }` |
| Success Response | `{ token: string, user: AuthUser }` |
| Error Response | `{ error: string }` |
| Current Status | ❌ BROKEN — Supabase `users` table missing (see RECOVERY_MIGRATION.md) |
| Fix Required | Run recovery migration `20260531_recovery_users_table.sql` |

### 2.2 Login (Password)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/login` |
| Auth Required | No |
| Request | `{ email, password }` |
| Success Response | `{ token: string, user: AuthUser }` |
| Error Response | `{ error: "Invalid email or password" }` |
| Current Status | ⚠️ DEGRADED — returns auth error (DB issue; cannot validate credentials) |

### 2.3 Login (Email OTP — Send)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/send-login-email-otp` |
| Auth Required | No |
| Request | `{ email }` |
| Success Response | `{ sessionToken: string, message: string }` |
| Current Status | ✅ HEALTHY (HTTP 200) |

### 2.4 Login (Email OTP — Verify)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/verify-login-email-otp` |
| Auth Required | No |
| Request | `{ sessionToken, code }` |
| Success Response | `{ token: string, user: AuthUser }` OR `{ newUser: true, email?, emailToken? }` |
| Current Status | ⚠️ DEGRADED (depends on DB) |

### 2.5 Login (SMS OTP — Send)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/send-otp` |
| Auth Required | No |
| Request | `{ phone }` (E.164 format: 234xxxxxxxxxx) |
| Success Response | `{ pinId: string, message: string }` |
| Current Status | ✅ HEALTHY |

### 2.6 Login (SMS OTP — Verify)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/verify-otp` |
| Auth Required | No |
| Request | `{ pinId, pin, phone }` |
| Success Response | `{ token: string, user: AuthUser }` OR `{ newUser: true, phone?, otpToken? }` |
| Current Status | ⚠️ DEGRADED (depends on DB) |

### 2.7 Logout / Session Management

| Property | Value |
|---|---|
| Revoke Current | `DELETE /auth/sessions/:id` |
| Revoke All | `DELETE /auth/sessions` |
| List Sessions | `GET /auth/sessions` |
| Auth Required | Bearer token |
| Current Status | ⚠️ DEGRADED (depends on DB) |

### 2.8 Password Reset (Request)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/request-password-reset` |
| Auth Required | No |
| Request | `{ email }` |
| Success Response | `{ message: string }` |
| HTTP Status | 200 (confirmed live) |
| Current Status | ✅ HEALTHY |

### 2.9 Password Reset (Confirm)

| Property | Value |
|---|---|
| Endpoint | `POST /auth/reset-password` |
| Auth Required | No |
| Request | `{ email, code, newPassword }` |
| Success Response | `{ message: string }` |
| Current Status | ⚠️ DEGRADED (depends on DB) |

### 2.10 Session Validation (Me)

| Property | Value |
|---|---|
| Endpoint | `GET /auth/me` |
| Auth Required | Bearer token |
| Success Response | `AuthUser { id, email, name, role, phone?, raldId?, createdAt }` |
| No Auth Response | `{ error: "Missing or invalid authorization header" }` (HTTP 401) |
| Current Status | ✅ HEALTHY (auth layer working) |

### 2.11 MFA

| Property | Value |
|---|---|
| Current Status | Not implemented in v1.2.0 |
| Planned | Future roadmap item |

### 2.12 SSO Exchange

| Property | Value |
|---|---|
| Endpoint | `POST /sso/exchange` |
| Auth Required | Bearer token |
| Request | `{ appId: string }` |
| Success Response | `{ token: string, appId: string, expiresIn: number }` |
| Purpose | Issue short-lived cross-app token for redirect flows |
| Current Status | ✅ HEALTHY (auth 401 when no token, correct) |

### 2.13 Clerk Exchange (Legacy)

| Property | Value |
|---|---|
| Endpoint | `POST /sso/clerk-exchange` |
| Auth Required | Bearer token |
| Request | `{ appId: string }` |
| Success Response | `{ clerkTicket: string, redirectUrl: string, appId: string }` |
| Purpose | Legacy compatibility for Clerk-based apps |
| Note | `publishableKey` currently empty — Clerk not configured |
| Current Status | ⚠️ DEGRADED (returns 401 without token; Clerk config empty) |

---

## 3. AuthUser Schema

```typescript
type AuthUser = {
  id: string;          // UUID
  email: string;
  name: string | null;
  role: "user" | "merchant" | "operator" | "admin";
  phone?: string | null;
  raldId?: string | null;  // Format: RALD-XXXXXX
  createdAt: string;       // ISO 8601
};
```

---

## 4. Token Model

| Property | Value |
|---|---|
| Type | JWT (Bearer) |
| Storage | localStorage (`rald_token`) |
| Validation | `GET /auth/me` — live validation against DB |
| SSO Tokens | Short-lived, issued per cross-app redirect |
| Rotation | On demand (new login issues new token) |

---

## 5. Abstraction Layer Design

### Interface: `IdentityProviderInterface`

```php
interface IdentityProviderInterface {
    public function login( string $email, string $password ): array;
    public function register( array $data ): array;
    public function logout( string $token ): bool;
    public function me( string $token ): array;
    public function requestPasswordReset( string $email ): bool;
    public function resetPassword( string $email, string $code, string $new_password ): bool;
    public function ssoExchange( string $token, string $app_id ): array;
    public function sendOtp( string $identity ): array;
    public function verifyOtp( string $identity, string $code, array $session ): array;
}
```

### Implementation: `RALDIdentityProvider`

All calls proxied to `auth.rald.cloud`. No Clerk SDKs, no direct Supabase access.

### Future: `RALDNativeIdentityProvider`

Drop-in replacement when RALD builds a native identity system. Zero plugin changes required.

---

## 6. Security Model

| Concern | Implementation |
|---|---|
| Token Transmission | HTTPS only, Bearer header |
| Token Storage (PHP) | WordPress user_meta, encrypted with SECURE_AUTH_KEY (AES-256-CBC) |
| Token Storage (Browser) | localStorage (profiles.rald.cloud only) |
| CSRF Protection | WordPress nonces on all admin forms |
| Capability Checks | `current_user_can()` on all admin endpoints |
| Rate Limiting | Enforced at `auth.rald.cloud` Worker level |
| Secret Rotation | Supported — re-encrypt on new SECURE_AUTH_KEY |

---

## 7. Current Deficiencies (Status: 2026-05-31)

| Issue | Severity | Fix |
|---|---|---|
| Supabase `users` table missing | CRITICAL | Run `20260531_recovery_users_table.sql` |
| profiles.rald.cloud no session auto-redirect | HIGH | Fixed in this PR (App.tsx) |
| Clerk publishableKey empty | MEDIUM | Configure or remove Clerk dependency |
| MFA not implemented | LOW | Future roadmap |

---

## 8. Plugin Adapter Compliance

RALD Connect communicates with `auth.rald.cloud` only.  
The `IdentityProviderInterface` abstraction isolates the plugin from any future provider change.  
Switching to a native RALD Identity service requires only a new `RALDNativeIdentityProvider` class — zero plugin or customer changes.
