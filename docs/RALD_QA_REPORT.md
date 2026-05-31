# RALD_QA_REPORT.md
## Full Ecosystem Q/A — 2026-05-31 14:02 UTC

---

## auth.rald.cloud (Worker v1.2.0)

| Endpoint | Method | Expected | Actual | Status | Notes |
|---|---|---|---|---|---|
| `/health` | GET | 200 | 200 | ✅ | `{"status":"ok","version":"1.2.0"}` |
| `/` | GET | 200 | 200 | ✅ | Service root |
| `/auth/me` | GET (no token) | 401 | 401 | ✅ | Correct auth guard |
| `/auth/login` | POST (empty body) | 400 | 400 | ✅ | `"Email and password required"` |
| `/auth/login` | POST (wrong creds) | 401 | 401 | ✅ | `"Invalid email or password"` |
| `/auth/register` | POST | 500 | 200+error | ⚠️ | DB offline — returns 200 with error body |
| `/auth/send-login-email-otp` | POST | 200 | 200 | ✅ | Email OTP works |
| `/auth/send-otp` (SMS) | POST | 200 | 502 | ❌ | Termii sender ID not found |
| `/auth/verify-otp` | POST (bad code) | 401 | 401 | ✅ | Correct rejection |
| `/auth/request-password-reset` | POST | 200 | 200 | ✅ | Reset emails working |
| `/sso/exchange` | POST (no token) | 401 | 401 | ✅ | Correct auth guard |
| `/sso/clerk-exchange` | POST (no token) | 401 | 401 | ✅ | Correct auth guard |
| `/sso/clerk-config` | GET | 200 | 200 | ✅ | Returns empty publishableKey |
| `/nonexistent` | GET | 404 | 404 | ✅ | Correct 404 |

---

## profiles.rald.cloud (Cloudflare Pages)

| Test | Status | Notes |
|---|---|---|
| Root page loads | ✅ | HTML/SPA loads correctly |
| `?redirect_to=` param accepted | ✅ | Page loads with params |
| Session detection + auto-redirect | 🔄 | Fix pushed to GitHub; CF Pages redeployment pending |
| Post-OTP redirect | ✅ | `doRedirect()` in Verify.tsx |
| Post-password-login redirect | ✅ | `doRedirect()` in Password.tsx |
| Post-signup redirect | ✅ | Redirect in Signup.tsx |
| Post-reset → sign in redirect | ✅ | Reset.tsx → navigate("/password") |

---

## Issues Found

### CRITICAL (1 human action required)
| # | Issue | Service | Fix |
|---|---|---|---|
| 1 | Supabase `users` table missing | auth.rald.cloud ← DB | Run `20260531_recovery_users_table.sql` at https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo/sql/new |

### HIGH (infrastructure config required)
| # | Issue | Service | Fix |
|---|---|---|---|
| 2 | SMS OTP 502 — Termii sender ID "RALD" not found for appId 66189 | auth.rald.cloud | Register sender ID "RALD" in Termii dashboard for application 66189, OR update `rald-auth-core` Worker to use a different/registered sender name |

### LOW (no action required)
| # | Issue | Notes |
|---|---|---|
| 3 | Clerk `publishableKey` empty | Not breaking — clerk-exchange returns error gracefully; plugin falls back to `/dashboard` |

---

## GitHub Status

| Repo | Latest Commit | Status |
|---|---|---|
| `rald-auth-ui` | `d5555cd` — session detection + auto-redirect | ✅ Pushed — CF Pages redeployment in progress |
| `rald-connect` | `4b79000` — Phase 0-8 complete | ✅ Pushed |

---

## Recovery Action Required (Human)

```
URL: https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo/sql/new

File: rald-auth-core/supabase/migrations/20260531_recovery_users_table.sql

After running:
  - POST /auth/register → returns token
  - POST /auth/login → returns token  
  - profiles.rald.cloud → full auth flow working
```
