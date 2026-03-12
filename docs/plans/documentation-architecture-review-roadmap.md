# Documentation Architecture Review Roadmap

Documentation-only fixes identified from a comprehensive architecture and design review conducted by reading all documentation without looking at code.

## Phase 1: Cross-Document Consistency Fixes (High Priority)

Factual inconsistencies that could confuse developers or AI assistants.

- [x] Fix request flow diagram in `docs/ai/architecture-map.md`
- [x] Align LLM orchestration modes across all docs (architecture-map, features, ADR-006)
- [x] Fix SSE vs WebSocket discrepancy in ADR-023
- [x] Fix settings flow ambiguity in architecture-map.md
- [x] Update backup format diagram in ADR-007

## Phase 2: Architectural Clarity (Medium Priority)

Missing explanations that help developers understand design decisions and boundaries.

- [x] Add settings decision flowchart (new `docs/ai/patterns/settings-overview.md`)
- [x] Document permission model policy (`can:admin` vs granular) in permission-checking pattern
- [x] Document ConfigServiceProvider failure/degraded mode in ADR-014
- [x] Document storage system pattern deviation in ADR-022
- [x] Clarify webhook ownership in features.md
- [x] Clarify API token location in features.md and context-loading.md
- [x] Clarify Council mode implementation status in ADR-006
- [x] Document cross-database backup restore in backup.md

## Phase 3: Developer Experience Additions (Medium Priority)

New content to help developers deploying or forking the project.

- [x] Add resource requirements to docker.md
- [x] Add `SANCTUM_STATEFUL_DOMAINS` to required variables in development.md
- [x] Document dual page registration requirement in recipes
- [x] Add missing cross-reference links (features, architecture, overview)
- [x] Add pre-release context to roadmaps.md

## Phase 4: Version and Accuracy Nits (Low Priority)

Small corrections verified against actual code.

- [x] Update framework versions (Next.js 16, Laravel 11, PHP 8.3+)
- [x] Document `rate.sensitive` middleware in security pattern
- [x] Fix audit log retention default inconsistency (ADR-023 says 90, actual is 365)

## Status

**Started:** 2026-02-05
**Completed:** 2026-02-05
