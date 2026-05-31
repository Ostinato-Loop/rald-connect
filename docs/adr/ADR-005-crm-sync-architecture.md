# ADR-005 — CRM Sync Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
Lead capture forms must sync submissions to RALD CRM systems. The plugin must not duplicate CRM storage.

## Decision
All form submissions dispatched to RALD Cloud via authenticated webhook. WordPress stores only a transient submission reference for idempotency checks (24h TTL). If webhook fails, WP Cron retries 3 times with exponential backoff.

## Alternatives Considered
- **Local WordPress CRM storage** — Rejected. Duplicates data. Violates "business logic in RALD Cloud" rule.
- **Direct Supabase insert** — Rejected. Bypasses RALD business logic layer.

## Consequences
- CRM data is always authoritative in RALD Cloud
- Network failure = deferred sync (not data loss)
