# RALD API Inventory
**Phase 1 — Pre-Build Discovery**
**Date:** 2026-05-31 | **Source:** rald-auth-core/src/index.ts, routes/*

---

## auth.rald.cloud — Full Route Map

Base URL: `https://auth.rald.cloud`
Runtime: Cloudflare Worker (Hono v4)
Auth: Bearer JWT (RALD_JWT_SECRET, 24h expiry)

### Public Routes (No auth required)

| Method | Path | Handler | File |
|--------|------|---------|------|
| GET | `/health` | Health check | index.ts |
| GET | `/` | API info | index.ts |
| POST | `/auth/register` | Create account | routes/auth.ts |
| POST | `/auth/login` | Password login | routes/auth.ts |
| POST | `/auth/logout` | Revoke session | routes/auth.ts |
| POST | `/auth/otp/send` | Send OTP (email/SMS) | routes/otp.ts |
| POST | `/auth/otp/verify` | Verify OTP code | routes/otp.ts |
| GET | `/sso/clerk-config` | Clerk public config | routes/clerk.ts |

### Authenticated Routes (Bearer token required)

| Method | Path | Handler | File |
|--------|------|---------|------|
| GET | `/auth/me` | Get current user | routes/auth.ts |
| PUT | `/auth/me` | Update profile | routes/auth.ts |
| POST | `/auth/change-password` | Change password | routes/auth.ts |
| GET | `/auth/sessions` | List active sessions | routes/auth.ts |
| DELETE | `/auth/sessions/:id` | Revoke session | routes/auth.ts |
| POST | `/sso/exchange` | Issue SSO session token | routes/sso.ts |
| POST | `/sso/verify` | Verify SSO session token | routes/sso.ts |
| POST | `/sso/clerk-exchange` | Clerk SSO bridge | routes/clerk.ts |
| GET | `/devices` | List trusted devices | routes/devices.ts |
| PUT | `/devices/:id/trust` | Mark device trusted | routes/devices.ts |
| DELETE | `/devices/:id` | Remove device | routes/devices.ts |

### Admin Routes (role: admin or operator)

| Method | Path | Handler | File |
|--------|------|---------|------|
| GET | `/admin/users` | List all users | routes/admin.ts |
| GET | `/admin/users/:id` | Get user | routes/admin.ts |
| PUT | `/admin/users/:id` | Update user | routes/admin.ts |
| DELETE | `/admin/users/:id` | Deactivate user | routes/admin.ts |
| POST | `/provision/grant` | Grant product access | routes/provision.ts |
| DELETE | `/provision/revoke` | Revoke product access | routes/provision.ts |
| GET | `/provision/check` | Check product access | routes/provision.ts |

---

## Key Request/Response Schemas

### POST /auth/register
```json
// Request
{
  "email": "user@example.com",
  "password": "min8chars",
  "name": "Full Name"
}
// Response 201
{
  "token": "eyJ...",
  "user": {
    "id": "uuid",
    "email": "user@example.com",
    "name": "Full Name",
    "role": "user",
    "rald_id": "RALD-XXXXXX",
    "email_verified": false,
    "created_at": "2026-05-31T..."
  }
}
```

### POST /auth/login
```json
// Request
{ "email": "user@example.com", "password": "..." }
// Response 200
{ "token": "eyJ...", "user": { ...same as register... } }
```

### POST /sso/exchange
```json
// Request (Authorization: Bearer <rald_token>)
{ "app_id": "rald-app", "redirect_to": "https://app.rald.cloud/dashboard" }
// Response 200
{
  "token": "short-lived-session-token",
  "expires_at": "2026-05-31T...",
  "redirect_url": "https://app.rald.cloud/dashboard?rald_token=..."
}
```

### POST /sso/clerk-exchange
```json
// Request (Authorization: Bearer <rald_token>)
{ "app_id": "rald-app" }
// Response 200
{
  "clerkTicket": "clerk_ticket_...",
  "redirectUrl": "https://app.rald.cloud/sso?__clerk_ticket=...",
  "appId": "rald-app"
}
// Fallback (no CLERK_SECRET_KEY configured)
{
  "clerkTicket": "",
  "redirectUrl": "https://app.rald.cloud",
  "appId": "rald-app",
  "fallback": true
}
```

### GET /auth/me
```json
// Headers: Authorization: Bearer <token>
// Response 200
{
  "id": "uuid",
  "email": "user@example.com",
  "name": "Full Name",
  "role": "user",
  "rald_id": "RALD-XXXXXX",
  "metadata": {},
  "is_active": true,
  "email_verified": true,
  "phone_verified": false,
  "created_at": "...",
  "updated_at": "..."
}
```

---

## api.rald.cloud — Relevant Routes for Plugin

Base URL: `https://api.rald.cloud`

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/bootstrap` | Bootstrap init (X-Bootstrap-Secret header) |
| GET | `/api/products` | Product catalogue |
| GET | `/api/services` | Service registry |
| POST | `/api/credentials` | Issue API credential |
| GET | `/api/user/:id/access` | Check user product access |

---

## WordPress Plugin — Required API Calls

The `rald-connect` plugin will make these calls:

| Plugin Action | API Call | Auth |
|--------------|---------|------|
| User login via RALD | `POST auth.rald.cloud/auth/login` | None |
| Token verification | `GET auth.rald.cloud/auth/me` | Bearer token |
| SSO redirect | `POST auth.rald.cloud/sso/exchange` | Bearer token |
| Product access check | `GET auth.rald.cloud/provision/check?product=X` | Bearer token |
| Sync user to WP | Maps RALD user → WP user object | Internal |

---

## Error Codes Reference

| HTTP | Code | Meaning |
|------|------|---------|
| 400 | `VALIDATION_ERROR` | Missing/invalid fields |
| 401 | `MISSING_TOKEN` | No Authorization header |
| 401 | `INVALID_TOKEN` | JWT expired or invalid |
| 403 | `INSUFFICIENT_PERMISSIONS` | Not admin/operator |
| 404 | `USER_NOT_FOUND` | No matching user in DB |
| 409 | `EMAIL_TAKEN` | Registration conflict |
| 500 | `DB_ERROR` | Supabase query failed (check users table!) |

---

## Rate Limits (Cloudflare Worker defaults)

- 1000 requests/10s per IP (Cloudflare zone default)
- OTP endpoints: additional server-side rate limit (10 per hour per email)
- No explicit rate limiting on auth endpoints beyond Cloudflare defaults

---

## Required Secrets (Worker)

| Secret | Description | Set On |
|--------|-------------|--------|
| `SUPABASE_URL` | `https://onxdcikfttdmnhofsuwo.supabase.co` | CF Worker secret |
| `SUPABASE_SERVICE_ROLE_KEY` | Service role key from Supabase | CF Worker secret |
| `RALD_JWT_SECRET` | 64-char base64url for JWT signing | CF Worker secret |
| `TERMII_API_KEY` | SMS OTP via Termii | CF Worker secret |
| `TERMII_SENDER_ID` | SMS sender name | CF Worker secret |
| `RESEND_API_KEY` | Email OTP/transactional via Resend | CF Worker secret |
| `CLERK_SECRET_KEY` | Clerk backend key (optional, enables clerk-exchange) | CF Worker secret |
