# DanaVision Contribution Requirements

This is a condensed checklist of mandatory requirements for contributing to DanaVision. For detailed explanations and examples, see [CONTRIBUTING.md](CONTRIBUTING.md).

> **⚠️ CRITICAL: These requirements are MANDATORY. Your changes will be rejected if they are not met.**

---

## Quick Reference: Requirements by Task Type

| Task Type | Backend Test | E2E Test | ADR Required? | Documentation Required? |
|-----------|-------------|----------|---------------|------------------------|
| New page | ✅ Pest feature test | ✅ Playwright spec | If new pattern | ✅ Update TypeScript types |
| New API endpoint | ✅ Pest feature test | If UI uses it | If significant | ✅ Update TypeScript types |
| Bug fix | ✅ Regression test | If UI affected | ❌ No | ❌ No |
| New component | ❌ No | ✅ If interactive | ❌ No | ❌ No |
| Database schema change | ✅ Migration test | ✅ If UI affected | ✅ Always | ✅ Update model docs |
| AI system changes | ✅ Service tests | ✅ If UI affected | ✅ Always | ✅ Update AI docs |
| Infrastructure changes | ✅ Deployment tests | ✅ Smoke test | ✅ Always | ✅ Update deploy docs |

---

## Pre-Completion Checklist

### 🔒 Security & Architecture (MANDATORY)
- [ ] User isolation is maintained (no cross-user data access)
- [ ] New resources have appropriate Policy with user_id checks
- [ ] Controllers call `$this->authorize()` for protected actions
- [ ] New fields added to Model AND TypeScript type

### 🧪 Testing (MANDATORY)
- [ ] **Backend tests pass**: `cd backend && ./vendor/bin/pest`
- [ ] **E2E tests pass**: `cd backend && npm run test:e2e`
- [ ] **New features have E2E tests**: Playwright specs in `backend/e2e/`
- [ ] **Bug fixes include regression test**: Test proving the fix

### 📝 Documentation (MANDATORY for significant changes)
- [ ] **ADR created** for architectural decisions (see `docs/adr/`)
- [ ] **TypeScript types updated** if API response changed
- [ ] **README updated** if setup process changed

### ✨ Code Quality
- [ ] Code follows existing patterns in the codebase
- [ ] No debug/console.log statements left in code
- [ ] Loading/error states handled in UI

---

## Test Requirements Summary

### Every Change Requires Tests

| Change Type | Test Type | Location |
|-------------|-----------|----------|
| New API endpoint/page | Pest PHP feature test | `backend/tests/Feature/` |
| New frontend page | Playwright E2E test | `backend/e2e/` |
| Bug fix | Regression test | Same location as feature |
| New service/utility | Pest PHP unit test | `backend/tests/Unit/` |

### Running Tests

```bash
# Backend tests (Pest PHP)
cd backend && ./vendor/bin/pest

# E2E tests (Playwright) - requires running app
cd backend && npm run test:e2e

# E2E with UI mode (debugging)
cd backend && npm run test:e2e:ui

# E2E headed mode (see browser)
cd backend && npm run test:e2e:headed
```

---

## ADR Requirements Summary

### When ADRs Are Required (MANDATORY)

ADRs MUST be written for:
- ✅ New features that introduce new patterns or architecture
- ✅ Database schema changes (affects all data)
- ✅ Authentication/authorization changes (security impact)
- ✅ New external integrations (APIs, services)
- ✅ AI system changes (core functionality)
- ✅ Infrastructure changes (deployment, Docker)

**ADR Location**: `docs/adr/`  
**ADR Template**: See [docs/adr/README.md](adr/README.md)

**Rule of thumb**: If future developers would ask "why was this done this way?", write an ADR.

---

## Documentation Requirements Summary

### When Documentation Updates Are Required

- ✅ **TypeScript types**: Always update if API response structure changes
- ✅ **README**: Update if setup process, installation, or configuration changes
- ✅ **Model docs**: Update if public APIs change

### API Contract Synchronization

These files MUST stay synchronized:
1. `backend/resources/js/types/index.ts` - TypeScript interfaces
2. `backend/app/Http/Controllers/*Controller.php` - Response data

---

## Quick Commands

```bash
# Run all backend tests
cd backend && ./vendor/bin/pest

# Run E2E tests (app must be running at localhost:8080)
cd backend && npm run test:e2e

# Build assets for Docker
docker exec danavision npm run build

# Clear Laravel caches
docker exec danavision php artisan cache:clear
docker exec danavision php artisan route:clear
docker exec danavision php artisan config:clear
```

---

## Need Help?

- 📖 **Detailed guidelines**: See [CONTRIBUTING.md](CONTRIBUTING.md)
- 🧪 **Testing guide**: See [DOCUMENTATION_TESTING.md](DOCUMENTATION_TESTING.md)
- 📋 **ADR template**: See [docs/adr/README.md](adr/README.md)

---

**Remember**: These requirements exist to maintain code quality, security, and maintainability. Following them ensures your contributions work correctly and don't break existing functionality.
