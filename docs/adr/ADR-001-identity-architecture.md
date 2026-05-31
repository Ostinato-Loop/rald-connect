# ADR-001 — Identity Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
RALD Connect must authenticate WordPress users against the RALD ecosystem. The existing system uses `auth.rald.cloud` (Cloudflare Worker + Supabase). Internally, RALD may use Clerk for some flows. WordPress has its own user table and session system.

## Decision
Use `auth.rald.cloud` as the sole identity authority. Never communicate with Clerk directly. Create shadow WordPress users that mirror RALD users. Abstract the identity provider behind `IdentityProviderInterface`.

## Alternatives Considered
- **Direct Supabase access** — Rejected. Would bypass auth business logic, create tight coupling, and expose DB credentials in plugin.
- **Clerk SDK for WordPress** — Rejected. Violates RALD Connect spec. Exposes Clerk branding.
- **Native WP auth only** — Rejected. Disconnects from RALD ecosystem.

## Consequences
- Future replacement of Clerk with native RALD Identity requires only a new `RALDNativeIdentityProvider` class
- Zero plugin changes, zero customer migrations, zero downtime
- Plugin remains Clerk-agnostic indefinitely
