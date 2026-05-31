# ADR-006 — Multi-Platform Connector Architecture
**Date:** 2026-05-31 | **Status:** ACCEPTED

## Context
RALD will need connectors for Wix, Shopify, Webflow, Squarespace, and custom websites. Architecture decisions made now must not block future platform support.

## Decision
All business logic belongs in RALD Cloud services. Platform connectors are thin adapters that:
1. Collect configuration
2. Bind to platform events
3. Render UI components
4. Proxy to RALD Cloud APIs

The `auth.rald.cloud` and `api.rald.cloud` surface is the cross-platform contract.

## Consequences
- Future connectors reuse the same RALD Cloud APIs
- Platform-specific code stays minimal
- RALD Cloud becomes the durable layer; connectors are disposable
