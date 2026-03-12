# Documentation Audit Roadmap

Comprehensive audit of all project documentation to ensure accuracy, completeness, and self-sufficiency for AI-assisted development.

**Priority:** MEDIUM  
**Dependencies:** None  
**Estimated Effort:** Large (8 phases)

## Goals

1. **AI Self-Sufficiency** - AI can implement features (notifications, emails, LLM, etc.) without asking clarifying questions
2. **Rebuild Capability** - Documentation is complete enough to rebuild the app exactly as it exists today
3. **Accuracy** - All documentation matches actual codebase implementation
4. **Completeness** - No undocumented features, patterns, or conventions
5. **File References** - All file paths point to existing files

---

## Phase 1: Cursor Rules Audit ✅ COMPLETE

Verify all 12 Cursor rules match actual codebase behavior, fix inconsistencies, and identify gaps.

### Task 1.1: Rule Accuracy Verification
- [x] **code-review.mdc** - Verify checklist items match current patterns
- [x] **dev-server-management.mdc** - Fix port reference (8081 → 8080)
- [x] **docker-component-installation.mdc** - Verify guidance is current
- [x] **documentation-first.mdc** - Verify all file references exist
- [x] **global-components.mdc** - Verify component locations, fix `frontend/hooks/` reference
- [x] **journaling-documentation.mdc** - Verify template sections
- [x] **local-docker-development.mdc** - Verify commands work
- [x] **logging-compliance.mdc** - Verify middleware and service references
- [x] **notification-template-variables.mdc** - Verify file references
- [x] **responsive-mobile-first.mdc** - Verify hook path and ADR reference
- [x] **roadmap-maintenance.mdc** - Verify section names in roadmaps.md
- [x] **tool-selection.mdc** - Verify link works

### Task 1.2: Identify Missing Rules
- [x] Review patterns.md and anti-patterns.md for frequently-needed guidance
- [x] Document recommendations for new rules (defer creation to later phases)

---

## Phase 2: AI Recipes Audit ✅ COMPLETE

Verify all 32 recipes in `docs/ai/recipes/` produce working code when followed.

### Task 2.1: API & Backend Recipes
- [x] **add-api-endpoint.md** - Verify route patterns, controller structure, middleware usage
- [x] **add-admin-protected-action.md** - Verify admin middleware, authorization patterns
- [x] **add-backup-destination.md** - Verify backup service patterns, destination implementation
- [x] **add-llm-provider.md** - Verify LLM service patterns, provider implementation
- [x] **add-notification-channel.md** - Verify notification channel patterns
- [x] **trigger-notifications.md** - Verify notification triggering patterns
- [x] **add-email-template.md** - Verify email template patterns
- [x] **add-notification-template.md** - Verify notification template patterns
- [x] **add-storage-provider.md** - Verify storage provider patterns
- [x] **add-searchable-model.md** - Verify search integration patterns
- [x] **add-searchable-page.md** - Verify page search patterns
- [x] **extend-backup-restore.md** - Verify backup extension patterns
- [x] **extend-logging.md** - Verify logging patterns
- [x] **add-access-logging.md** - Verify HIPAA access logging patterns
- [x] **trigger-audit-logging.md** - Verify audit logging patterns
- [x] **add-auditable-action.md** - Verify auditable action patterns
- [x] **add-new-permission.md** - Verify permission patterns
- [x] **create-custom-group.md** - Verify group creation patterns
- [x] **assign-user-to-groups.md** - Verify group assignment patterns

### Task 2.2: Frontend Recipes
- [x] **add-config-page.md** - Verify config page patterns, form handling, API integration
- [x] **add-settings-page.md** - Verify settings page patterns, SettingService usage
- [x] **add-dashboard-widget.md** - Verify dashboard widget patterns
- [x] **add-ui-component.md** - Verify UI component patterns, shadcn/ui usage
- [x] **make-component-responsive.md** - Verify responsive patterns, useIsMobile hook
- [x] **add-collapsible-section.md** - Verify CollapsibleCard patterns
- [x] **add-provider-icon.md** - Verify ProviderIcon patterns
- [x] **add-configuration-menu-item.md** - Verify navigation patterns

### Task 2.3: Cross-Cutting Recipes
- [x] **add-sso-provider.md** - Verify SSO implementation patterns
- [x] **add-tests.md** - Verify test patterns, file locations, conventions
- [x] **code-review.md** - Verify checklist items are still relevant
- [x] **keep-notification-template-variables-up-to-date.md** - Verify variable sync patterns

---

## Phase 3: Patterns & Anti-Patterns Audit ✅ COMPLETE

Verify `docs/ai/patterns.md` and `docs/ai/anti-patterns.md` code examples match actual codebase.

### Task 3.1: Backend Patterns
- [x] Verify controller patterns match actual controllers (fixed: now uses ApiResponseTrait)
- [x] Verify service layer patterns match actual services
- [x] Verify SettingService patterns match implementation
- [x] Verify AuditService patterns match implementation
- [x] Verify AccessLogService patterns match implementation
- [x] Verify Permission/Group patterns match implementation
- [x] Verify Channel/Provider patterns match implementations
- [x] Verify SearchService patterns match implementation
- [x] Verify EmailTemplateService patterns match implementation
- [x] Verify NotificationTemplateService patterns match implementation

### Task 3.2: Frontend Patterns
- [x] Verify API call patterns match `frontend/lib/api.ts` (fixed: now shows axios implementation)
- [x] Verify component patterns match actual components
- [x] Verify form handling patterns match actual usage
- [x] Verify error handling patterns match actual usage
- [x] Verify responsive patterns match actual usage
- [x] Verify CollapsibleCard patterns match implementation
- [x] Verify ProviderIcon patterns match implementation
- [x] Verify PWA patterns match implementation

### Task 3.3: Anti-Patterns Review
- [x] Verify each anti-pattern is still relevant
- [x] Verify "correct" examples actually work
- [x] Check for anti-patterns that are now acceptable
- [x] Add new anti-patterns discovered during development (added duplicated utilities anti-pattern)

### Task 3.4: Pattern Completeness
- [x] Identify patterns used in codebase but not documented (found 9 missing patterns)
- [x] Identify deprecated patterns still documented (none found)
- [x] Add missing patterns discovered during audit (added: redirect pages, test connection, file download, error extraction, useOnline, typed confirmation, first-user-admin, multi-channel error handling, filename validation)

---

## Phase 4: ADR & Architecture Audit ✅ COMPLETE

Verify Architecture Decision Records and architecture documentation are current.

### Task 4.1: ADR File Reference Verification
- [x] Verify all ADR file references in `docs/architecture.md` point to existing files
- [x] Verify key implementation files listed in each ADR exist
- [x] Update file references for moved/renamed files (fixed ADR-001 paths)

### Task 4.2: ADR Content Review
- [x] **ADR-002** (Authentication) - Verified matches current auth implementation
- [x] **ADR-003** (SSO) - Verified matches current SSO providers (7 providers)
- [x] **ADR-005** (Notifications) - Verified matches current notification channels (13 channels)
- [x] **ADR-006** (LLM) - Verified matches current LLM providers (6 providers)
- [x] **ADR-007** (Backup) - Verified matches current backup implementation
- [x] **ADR-014** (Settings) - Verified matches SettingService implementation
- [x] **ADR-016** (Email Templates) - Verified matches email template implementation
- [x] **ADR-017** (Notification Templates) - Verified matches notification template implementation
- [x] **ADR-019** (PWA) - Verified matches current PWA implementation
- [x] All additional ADRs (001, 004, 008, 009, 010, 011, 012, 013, 015, 018) reviewed

### Task 4.3: Missing ADRs
- [x] Identify architectural decisions not documented
- [x] Create ADRs for undocumented decisions:
  - ADR-020: User Groups and Permissions System
  - ADR-021: Search with Meilisearch Integration
  - ADR-022: Storage Provider System
  - ADR-023: Audit Logging System

---

## Phase 5: Features Documentation Audit ✅ COMPLETE

Ensure `docs/features.md` is complete and accurately describes all implemented features.

### Task 5.1: Feature Coverage
- [x] Compare features.md against actual implemented features
- [x] Verify all Configuration sections are documented
- [x] Verify all user-facing features are documented
- [x] Verify all admin features are documented

### Task 5.2: Feature Accuracy
- [x] Verify capability lists are accurate
- [x] Verify channel/provider lists are current
- [x] Verify settings and toggles are documented
- [x] Verify API endpoint references are correct

### Task 5.3: Recent Features
- [x] Verify PWA features are fully documented
- [x] Verify Storage Settings features are fully documented
- [x] Verify Search features are fully documented
- [x] Verify User Groups features are fully documented

---

## Phase 6: API Reference Audit ✅ COMPLETE

Ensure API documentation matches actual implementation.

### Task 6.1: Route Coverage
- [x] Compare `backend/routes/api.php` against `docs/api-reference.md`
- [x] Identify undocumented endpoints
- [x] Identify documented endpoints that no longer exist

### Task 6.2: OpenAPI Specification
- [x] Verify `docs/api/openapi.yaml` matches actual endpoints
- [x] Verify request/response schemas are accurate
- [x] Verify authentication requirements are documented

### Task 6.3: API Documentation Updates
- [x] Document missing endpoints (added ~75 missing endpoints to api-reference.md)
- [x] Remove obsolete endpoint documentation
- [x] Update incorrect request/response examples
- [x] Added new tags and paths to openapi.yaml (Passkeys, User Settings, Search, Dashboard, User Management, User Groups, Audit Logs, Access Logs, Email Templates, Notification Templates, Storage, Webhooks, Jobs, Branding)
- [x] Added new component schemas (Passkey, ApiToken, SearchResults, UserGroup, AuditLog, AuditStats, EmailTemplate)

---

## Phase 7: Context Loading Audit ✅ COMPLETE

Verify `docs/ai/context-loading.md` file references exist and task types are complete.

### Task 7.1: File Reference Verification
- [x] Verify all file paths exist in the codebase
- [x] Verify ADR references are current
- [x] Verify service/component references are current
- [x] Remove references to deleted/moved files (none found - all paths valid)

### Task 7.2: Task Type Coverage
- [x] Verify task types cover all common development scenarios
- [x] Add missing task types if needed (added Webhooks Work, API Tokens Work sections)
- [x] Update file recommendations based on actual development patterns

### Task 7.3: Recipe Cross-References
- [x] Verify recipe links in context-loading.md are correct (all 17 recipe references valid)
- [x] Add missing recipe references for task types

---

## Phase 8: Cross-Reference & Completeness Verification ✅ COMPLETE

Final validation that documentation is self-sufficient.

### Task 8.1: Internal Link Verification
- [x] Verify all internal links work (docs referencing other docs)
- [x] Fix broken links (fixed ADR-014 path in README.md)
- [x] Add missing cross-references (added 5 missing journal entries to roadmaps.md)

### Task 8.2: Rebuild Test
- [x] Walk through building a new feature using only documentation (notification channel walkthrough)
- [x] Document any gaps discovered (none - documentation is comprehensive)
- [x] Fill gaps with additional documentation (N/A)

### Task 8.3: AI README Updates
- [x] Update `docs/ai/README.md` quick links table with all recipes (added 6 missing recipes)
- [x] Update gotchas section with any new discoveries (added admin group-based, audit actions)
- [x] Ensure development workflow is accurate (verified)

### Task 8.4: Documentation Cleanup
- [x] Check for duplicated information across files (none found)
- [x] Ensure consistent terminology (verified)
- [x] Remove outdated version references (N/A)
- [x] Add "Last Audited" notes where appropriate (added in journal entry)

### Task 8.5: Journal Entry
- [x] Create journal entry documenting audit findings
- [x] Document recommendations for ongoing documentation maintenance

---

## Success Criteria

- All recipes produce working code when followed
- All patterns match actual codebase implementations
- All file references point to existing files
- All ADRs reflect current architectural decisions
- Features.md covers all implemented features
- API documentation matches all endpoints
- An AI can implement any documented feature without asking clarifying questions
- Documentation is sufficient to rebuild the application

## Notes

- This audit can be done incrementally (one phase at a time)
- Each recipe/pattern should be tested by following it for a real task
- Findings should be documented in a journal entry
- Consider adding automated checks for file references in CI
- Rules identified as potentially useful should be created after Phase 3
