# ADR-008 — Deployment Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
RALD Connect must be distributable as a standard WordPress plugin zip. CI/CD must be automated.

## Decision
GitHub Actions workflow:
1. On tag push (`v*.*.*`)
2. Install Composer dependencies
3. `pnpm install && pnpm build` (React admin)
4. Package into `rald-connect-{version}.zip`
5. Create GitHub Release with zip attachment

Plugin update checks against GitHub Releases API. No WordPress.org dependency for initial distribution.

## Alternatives Considered
- **WordPress.org SVN** — Deferred. Requires review process. GitHub Releases faster for initial rollout.
- **Private WP update server** — Overkill for v1.

## Consequences
- Customers install via GitHub Release URL or direct upload
- WordPress.org submission is a future milestone
- CI/CD is fully automated on tag
