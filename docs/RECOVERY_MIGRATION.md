# profiles.rald.cloud — Recovery Guide
**Incident: users table dropped by previous agent session**
**Date:** 2026-05-31 | **Severity:** Critical | **Status:** Fix Ready

---

## What Happened

A previous Replit Agent session dropped the `users` table from the shared Supabase project (`onxdcikfttdmnhofsuwo`). 7 rows of user data were lost. Additionally, FK constraints were dropped from `referral_codes`, `referrals`, `sessions`, and `waitlist` tables.

The `auth.rald.cloud` Cloudflare Worker is **fully operational** — all routes exist and are correctly deployed. The `profiles.rald.cloud` frontend is **fully correct** — the Cloudflare Pages deployment is up to date.

The **only issue** is the missing `users` table in Supabase.

---

## What is NOT affected

- `auth.rald.cloud` Worker code — ✅ unchanged
- `profiles.rald.cloud` frontend — ✅ unchanged  
- `referral_codes`, `referrals`, `sessions`, `waitlist` — ✅ data intact (FK enforcement only was dropped)
- All other Supabase tables — ✅ fully intact
- Other projects on the same Supabase instance — ✅ not affected by this recovery

---

## Recovery Steps

### Step 1: Run the recovery SQL

1. Open Supabase SQL Editor:
   **https://supabase.com/dashboard/project/onxdcikfttdmnhofsuwo/sql/new**

2. Paste the entire contents of:
   `rald-auth-core/supabase/migrations/20260531_recovery_users_table.sql`

3. Click **Run**

4. Expected output:
   ```
   CREATE TABLE
   CREATE INDEX (×4)
   CREATE FUNCTION
   CREATE TRIGGER (×2)
   INSERT 0 1
   ALTER TABLE (×5)
   ```

### Step 2: Update the admin password

The recovery SQL inserts `admin@rald.cloud` with a placeholder bcrypt hash.
Update it with a real password hash:

```sql
-- Generate hash: use https://bcrypt-generator.com or run locally:
-- node -e "require('bcrypt').hash('your-password', 10, (e,h) => console.log(h))"

UPDATE users 
SET password_hash = '$2b$10$YOUR_REAL_HASH_HERE'
WHERE email = 'admin@rald.cloud';
```

### Step 3: Verify

```sql
-- Check users table exists and has admin
SELECT id, email, role, rald_id, created_at FROM users;

-- Check FK constraints are restored
SELECT conname, conrelid::regclass, confrelid::regclass 
FROM pg_constraint 
WHERE contype = 'f' 
AND confrelid = 'users'::regclass;

-- Check all RALD tables are intact
SELECT table_name, (SELECT count(*) FROM information_schema.columns c2 WHERE c2.table_name = t.table_name) as col_count
FROM information_schema.tables t
WHERE table_schema = 'public'
ORDER BY table_name;
```

### Step 4: Test profiles.rald.cloud

1. Visit https://profiles.rald.cloud
2. Attempt to register a new account
3. Attempt to log in with `admin@rald.cloud`
4. Verify the SSO redirect works

---

## Why FK constraints use NOT VALID

The `NOT VALID` clause on restored FK constraints means:
- **Existing rows** are not checked (they may have orphaned `user_id` references from the period the table was missing)
- **New rows** are fully validated against the recreated `users` table
- This prevents the constraint from failing due to historical orphaned references

To eventually validate all rows (once orphaned rows are cleaned or the data is acceptable):
```sql
ALTER TABLE sessions      VALIDATE CONSTRAINT fk_sessions_user_id;
ALTER TABLE referral_codes VALIDATE CONSTRAINT fk_referral_codes_user_id;
ALTER TABLE referrals     VALIDATE CONSTRAINT fk_referrals_referee_id;
ALTER TABLE waitlist      VALIDATE CONSTRAINT fk_waitlist_user_id;
```

---

## Prevention

1. **Never run `DROP TABLE` on the shared Supabase project without explicit approval**
2. **Always use `IF NOT EXISTS` in migrations** — already enforced in all RALD migrations
3. **Use `NOT VALID` when restoring FK constraints** — avoids blocking on historical data
4. The Supabase project dashboard has Point-in-Time Recovery enabled (check Settings → Backups)
