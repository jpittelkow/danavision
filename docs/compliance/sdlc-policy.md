# Secure Development Lifecycle Policy

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]  
**Document Owner:** [TECH_LEAD_OR_SECURITY_CONTACT]

> **Customize**: Replace bracketed placeholders. Adapt to your team size, technology stack, and risk profile.

---

## 1. Overview

This Secure Development Lifecycle (SDLC) policy establishes security requirements integrated into all phases of software development at [COMPANY_NAME].

---

## 2. Secure Coding Standards

### 2.1 General Principles
- Validate all user input; never trust client-side validation alone
- Use parameterized queries; never concatenate user input into SQL
- Escape output to prevent XSS; use framework escaping where available
- Apply principle of least privilege for database and service accounts
- Fail securely; default to deny on errors

### 2.2 Technology-Specific Guidance
- **PHP/Laravel**: Use Eloquent ORM, request validation, CSRF protection
- **JavaScript/TypeScript**: Sanitize DOM output, avoid `eval()`, use CSP
- **SQL**: Use prepared statements or query builder; never raw concatenation

> **Customize**: Add standards for other languages or frameworks you use.

---

## 3. Code Review Requirements

### 3.1 Review Criteria
- All code changes require review before merge
- Security-sensitive changes require [ONE/TWO] approvers
- Review checklist includes: input validation, authentication/authorization, sensitive data handling, error handling

### 3.2 Security-Sensitive Areas
- Authentication and authorization logic
- File upload handling
- External API integrations
- Database migrations affecting sensitive tables
- Configuration and secrets handling

---

## 4. Security Testing Requirements

### 4.1 Automated Scanning (SAST)
Sourdough integrates automated security scanning:

| Tool | Scope | When to Run |
|------|-------|-------------|
| **PHPStan** | PHP backend | `composer phpstan` in `backend/` |
| **ESLint (security plugin)** | Frontend JS/TS | `npm run lint` in `frontend/` |
| **Semgrep** | Full codebase | CI on push/PR; locally via `./scripts/security-scan.sh` |

> **Customize**: Document any additional tools (e.g., Snyk, SonarQube) and required pass criteria.

### 4.2 Local Development
- Run `./scripts/security-scan.sh` before pushing
- Use `--quick` to skip Semgrep for faster iteration
- Use `--fix` to auto-fix ESLint issues

### 4.3 Pre-Production
- [ ] All SAST checks pass in CI
- [ ] No known high/critical vulnerabilities in dependencies
- [ ] Manual security review for high-risk changes

---

## 5. Dependency Management

### 5.1 Requirements
- Dependencies from official package registries (npm, Packagist)
- Pin versions in lock files (package-lock.json, composer.lock)
- Review dependency changes in PRs

### 5.2 Vulnerability Scanning
- Run `composer audit` (PHP) and `npm audit` (Node) regularly
- Address high/critical vulnerabilities within [NUMBER] days
- Document accepted risks with mitigation and approval

### 5.3 Abandoned Packages
- Monitor for unmaintained dependencies
- Plan migration path for abandoned packages with known vulnerabilities

---

## 6. Deployment Procedures

### 6.1 Environment Separation
- Development, staging, and production environments are separate
- Production credentials never in code or non-production configs
- Use environment variables or secrets management

### 6.2 Deployment Checklist
- [ ] All tests pass
- [ ] Security scan passed
- [ ] Database migrations reviewed
- [ ] Rollback procedure documented
- [ ] Monitoring/alerting verified

### 6.3 Change Management
- Production changes require [approval process]
- Deployment during [maintenance window / business hours]
- Post-deployment verification within [NUMBER] minutes

---

## 7. Incident Response for Vulnerabilities

- Critical vulnerabilities: Patch within [24/48/72] hours
- High vulnerabilities: Patch within [NUMBER] days
- Coordinate with Incident Response Plan for active exploitation

---

## 8. Training and Awareness

- Developers receive secure coding training [ANNUALLY]
- New hires complete security onboarding before code access
- Security updates communicated via [CHANNEL]

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
