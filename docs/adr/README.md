# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for DanaVision.

## What is an ADR?

An ADR is a document that captures an important architectural decision made along with its context and consequences. ADRs help future developers understand why certain decisions were made.

## ADR Index

| ID | Title | Status | Date |
|----|-------|--------|------|
| [001](001-tech-stack.md) | Technology Stack | Accepted | 2024-12-28 |
| [002](002-ai-provider-abstraction.md) | AI Provider Abstraction | Accepted | 2024-12-29 |
| [003](003-price-api-abstraction.md) | Price API Provider Abstraction | Accepted | 2024-12-30 |
| [004](004-mobile-first-architecture.md) | Mobile-First Architecture | Accepted | 2024-12-30 |
| [005](005-user-based-lists.md) | User-Based Lists with Sharing | Accepted | 2024-12-30 |
| [006](006-email-notifications.md) | Email Notification System | Accepted | 2024-12-31 |
| [007](007-smart-add-feature.md) | Smart Add Feature with AI Aggregation | Accepted | 2026-01-02 |
| [008](008-e2e-testing-strategy.md) | E2E Testing Strategy with Playwright | Accepted | 2026-01-02 |
| [009](009-ai-job-system.md) | AI Background Job System | Accepted | 2026-01-04 |
| [010](010-serp-ai-aggregation.md) | SERP API + AI Aggregation Architecture | Accepted | 2026-01-04 |
| [011](011-firecrawl-price-discovery.md) | Firecrawl Web Crawler Integration | Accepted | 2026-01-04 |
| [012](012-store-registry-architecture.md) | Store Registry Architecture | Accepted | 2026-01-04 |
| [013](013-nearby-store-discovery.md) | Nearby Store Discovery | Accepted | 2026-01-04 |
| [014](014-tiered-search-url-detection.md) | Tiered Search URL Detection System | Accepted | 2026-01-04 |
| [015](015-dashboard-redesign.md) | Dashboard Redesign and Navigation Updates | Accepted | 2026-01-05 |
| [016](016-crawl4ai-integration.md) | Crawl4AI Integration for Price Discovery | Superseded (reverted to Firecrawl) | 2026-01-24 |

## When to Write an ADR

ADRs MUST be written for:
- ✅ New features that introduce new patterns or architecture
- ✅ Database schema changes (affects all data)
- ✅ Authentication/authorization changes (security impact)
- ✅ New external integrations (APIs, services)
- ✅ AI system changes (core functionality)
- ✅ Infrastructure changes (deployment, Docker)
- ✅ Any decision where someone would ask: "why was this done this way?"

**Rule of thumb**: If future developers would question the decision, write an ADR.

## Creating a New ADR

### Naming Convention

ADR files are named using the pattern: `NNN-short-title.md`

- `NNN`: Three-digit sequential number (001, 002, etc.)
- `short-title`: Lowercase, hyphen-separated description

Example: `009-caching-strategy.md`

### Template

```markdown
# ADR [NUMBER]: [TITLE]

## Status

[Proposed | Accepted | Deprecated | Superseded]

## Date

[YYYY-MM-DD]

## Context

[Describe the context and problem statement. What issue are we addressing?]

## Decision

[Describe the decision and rationale. What did we decide to do and why?]

## Consequences

### Positive
- [List positive consequences/benefits]

### Negative
- [List negative consequences/trade-offs]

## Related Decisions

- [Links to related ADRs if any]
```

## Status Definitions

- **Proposed**: Under discussion, not yet accepted
- **Accepted**: Decision has been made and is in effect
- **Deprecated**: No longer relevant but kept for historical reference
- **Superseded**: Replaced by a newer decision (link to new ADR)

## Updating ADRs

ADRs should generally be immutable once accepted. If a decision needs to change:

1. Create a new ADR with the new decision
2. Mark the old ADR as "Superseded by ADR-XXX"
3. Link to the new ADR from the old one

This preserves the historical record of decisions and their evolution.
