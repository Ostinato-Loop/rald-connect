# ADR-002 — SDK Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
No pre-existing PHP SDK found in the Ostinato-Loop org. Multiple future platform connectors (Wix, Shopify, Webflow) will need the same RALD API client capabilities.

## Decision
The PHP adapter classes in RALD Connect serve as the de facto SDK v1. The `RaldAuthClient` class is the HTTP client foundation. Future extraction to a standalone `rald-php-sdk` Composer package is planned but not blocking.

## Alternatives Considered
- **Extract SDK immediately** — Deferred. No other PHP consumers yet; premature abstraction.
- **Use Guzzle/HTTPlug** — Rejected. Adds Composer dependency; WP_HTTP is sufficient and avoids version conflicts.

## Consequences
- RALD Connect is both a plugin and the reference PHP SDK implementation
- SDK extraction is a future task when second PHP consumer exists
