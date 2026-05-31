# ADR-003 — Plugin Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
RALD Connect must be modular, lightweight, and maintainable. WordPress has conventions around plugin structure that must be followed for marketplace submission.

## Decision
Single-file bootstrap (`rald-connect.php`) loads a singleton `RaldConnect` class. Each module is a separate class loaded conditionally. React admin dashboard built with Vite + TypeScript, bundled into `admin/js/dist/`. REST API organized into separate classes per module.

## Alternatives Considered
- **Monolithic plugin file** — Rejected. Unmaintainable at scale.
- **Multiple plugins** — Rejected. User experience friction; dependency management burden.

## Consequences
- Any module can be disabled without affecting others
- Admin UI is decoupled from PHP; can be updated independently
- REST API versioned at `/v1/` from day one
