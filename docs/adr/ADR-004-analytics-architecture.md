# ADR-004 — Analytics Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
RALD has an existing analytics platform (RALDTICS). The plugin must not create a second analytics system.

## Decision
Inject RALDTICS tracking script via `wp_enqueue_scripts`. All beacon events proxied through `/wp-json/rald-connect/v1/analytics/beacon` (avoids ad-blocker bypass issues) then forwarded to RALD analytics infrastructure. No local storage of analytics data.

## Alternatives Considered
- **Direct RALDTICS SDK** — Accepted as future option; current implementation uses server-side proxy for reliability.
- **Google Analytics / Plausible** — Rejected. Third-party systems. RALDTICS is the RALD standard.

## Consequences
- Analytics data lives entirely in RALD Cloud
- WordPress server acts as a trusted relay (reduces client-side blocking)
