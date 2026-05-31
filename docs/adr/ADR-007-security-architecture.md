# ADR-007 — Security Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
WordPress plugins are frequent attack surfaces. RALD tokens must be protected. All API calls must be authenticated.

## Decision
- All RALD tokens stored encrypted in user_meta using AES-256-CBC with `SECURE_AUTH_KEY` as key material
- All admin AJAX/REST calls validated with WordPress nonces
- All REST endpoints enforce `current_user_can()` where appropriate
- Input sanitized with WP sanitization functions before any processing
- Output escaped with WP escaping functions before any rendering
- No secrets in source code or client-side bundles
- API keys stored in `wp_options` encrypted at rest

## Alternatives Considered
- **Plain-text token storage** — Rejected. User_meta is readable by other plugins.
- **Cookie-based token storage** — Rejected. More complex, same security surface.

## Consequences
- Token compromise requires WP database access (already elevated privilege)
- Secret rotation is supported: re-encrypt all tokens when `SECURE_AUTH_KEY` changes
