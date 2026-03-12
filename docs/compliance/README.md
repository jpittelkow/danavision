# Compliance Documentation

Template compliance documents for SOC 2 Type II, ISO 27001, and general security policies. **Customize these templates for your own project**—replace placeholders and adapt content to your organization.

## Quick Start

1. **Copy** the `docs/compliance/` directory into your project (or fork Sourdough).
2. **Replace** all bracketed placeholders (e.g., `[COMPANY_NAME]`, `[DATE]`, `[CONTACT_EMAIL]`).
3. **Review** each document and adapt to your context, risk appetite, and regulatory requirements.
4. **Approve** policies with appropriate authority and publish to your team.

## Placeholder Reference

| Placeholder | Replace With |
|-------------|--------------|
| `[COMPANY_NAME]` | Your organization name |
| `[DATE]` | Document date (YYYY-MM-DD) |
| `[CONTACT_EMAIL]` | Security/incident contact email |
| `[SECURITY_CONTACT_NAME]` | Person responsible for security |
| `[NUMBER]` | Numeric value (days, hours, etc.) |
| `[PHONE_NUMBER]` | On-call or support number |

Look for **Customize** callouts in each document for additional guidance.

---

## Document Index

### Core Policies

| Document | Purpose |
|----------|---------|
| [Security Policy](security-policy.md) | Information security policy—principles, auth, encryption, acceptable use |
| [Incident Response Plan](incident-response-plan.md) | Security incident detection, containment, recovery procedures |
| [Business Continuity Plan](business-continuity-plan.md) | BCP/DR, backup procedures, recovery objectives |
| [SDLC Policy](sdlc-policy.md) | Secure development lifecycle—coding standards, reviews, testing |
| [Data Handling Policy](data-handling-policy.md) | Data classification, retention, disposal, privacy |
| [Access Control Policy](access-control-policy.md) | User provisioning, deprovisioning, RBAC, privileged access |
| [Security Awareness Guidelines](security-awareness-guidelines.md) | Employee security awareness—passwords, phishing, incident reporting |

### SOC 2 Type II

| Document | Purpose |
|----------|---------|
| [SOC 2 Overview](soc2/README.md) | Trust Service Criteria, audit prep, evidence guidance |
| [Security Controls (CC)](soc2/security-controls.md) | Common Criteria CC1–CC9 control mapping |
| [Availability Controls](soc2/availability-controls.md) | A1 criteria, capacity planning, SLA template |
| [Processing Integrity Controls](soc2/processing-integrity-controls.md) | PI1 criteria, data validation, processing accuracy |
| [Confidentiality Controls](soc2/confidentiality-controls.md) | C1 criteria, confidential information identification and disposal |
| [Privacy Controls](soc2/privacy-controls.md) | P1 criteria, privacy notice, DSAR process |

### ISO 27001

| Document | Purpose |
|----------|---------|
| [ISO 27001 Overview](iso27001/README.md) | Annex A overview, certification process |
| [ISMS Framework](iso27001/isms-framework.md) | Policy template, risk assessment, SoA, security objectives and metrics |
| [Cryptographic Controls (A.10)](iso27001/cryptographic-controls.md) | Encryption policy, key management |
| [Operations Security (A.12)](iso27001/operations-security.md) | Operational procedures, backup, logging, vulnerability management |
| [Communications Security (A.13)](iso27001/communications-security.md) | Network security, information transfer |
| [Supplier Relationships (A.15)](iso27001/supplier-relationships.md) | Third-party security requirements, vendor assessment |

---

## Sourdough Feature References

These templates reference Sourdough features where applicable:

| Feature | Location | Use Case |
|---------|----------|----------|
| Audit logs | Configuration > Logs | Incident investigation, compliance evidence |
| Access logs | Configuration > Logs (when enabled) | HIPAA-style access tracking |
| User groups / RBAC | Configuration > User Groups | Access control evidence |
| 2FA / Passkeys | Configuration > Security | Authentication controls |
| Backup | Configuration > Backup | BCP/DR, availability |
| Security scanning | `./scripts/security-scan.sh` | SDLC, vulnerability management |

See [features.md](../features.md) and [architecture.md](../architecture.md) for more.

---

## Compliance Roadmap

For implementation guidance, see [Security Compliance Roadmap](../plans/security-compliance-roadmap.md).
