# SOC 2 Type II Compliance Guide

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: This guide helps prepare for SOC 2 Type II audits. Replace placeholders and adapt to your scope and auditor requirements.

---

## 1. Trust Service Criteria Overview

SOC 2 reports are based on the AICPA Trust Service Criteria (TSC). The five categories are:

| Criteria | Focus | Key Areas |
|----------|-------|-----------|
| **Security (Common Criteria)** | Foundational controls | Access control, risk mitigation, monitoring |
| **Availability** | System availability | Uptime, capacity, incident response |
| **Processing Integrity** | Processing completeness and accuracy | Validation, error handling, quality |
| **Confidentiality** | Confidential information protection | Classification, encryption, disposal |
| **Privacy** | Personal information handling | Notice, consent, retention, disposal |

Most organizations start with **Security** (required) and add **Availability** for SaaS. Add **Confidentiality** or **Privacy** if handling sensitive or personal data.

---

## 2. Audit Preparation Checklist

### 2.1 Pre-Audit (6–12 Months Before)
- [ ] Define scope: systems, processes, locations
- [ ] Select Trust Service Criteria (Security + others)
- [ ] Implement controls per criteria
- [ ] Document policies and procedures
- [ ] Begin evidence collection (operational evidence over 6–12 months)

### 2.2 Documentation Ready
- [ ] Information security policy
- [ ] Access control policy
- [ ] Incident response plan
- [ ] Business continuity plan
- [ ] Risk assessment
- [ ] Vendor management procedures (if applicable)

### 2.3 Evidence Types
- Screenshots of configurations
- Log extracts (audit logs, access logs)
- Policy versions with approval dates
- Training records
- Change management records
- Incident reports and resolutions

---

## 3. Evidence Collection Guidance

### 3.1 Access Control (CC6)
- User provisioning/deprovisioning procedures
- Role definitions and assignments
- Access reviews (quarterly/semi-annual)
- MFA configuration evidence

### 3.2 Monitoring (CC7)
- Log retention configuration
- Alert rules and response procedures
- Vulnerability scan results
- Penetration test reports (if applicable)

### 3.3 Change Management (CC8)
- Deployment procedures
- Code review evidence
- Approval workflows

### 3.4 Risk Mitigation (CC9)
- Risk assessment document
- Vendor assessments
- Insurance (cyber, E&O)

---

## 4. Sourdough Mapping

| TSC Area | Sourdough Feature | Evidence Location |
|----------|-------------------|-------------------|
| Access control | User groups, RBAC, 2FA, passkeys | Configuration > Security, User Groups |
| Audit logging | Audit logs, access logs | Configuration > Logs |
| Encryption | HTTPS, Laravel encryption | nginx, config |
| Backup | Backup feature | Configuration > Backup |
| Session management | Sanctum token expiration | config/sanctum.php |

---

## 5. Related Templates

- [Security Controls (CC Series)](security-controls.md)
- [Availability Controls](availability-controls.md)
- [Processing Integrity Controls](processing-integrity-controls.md)
- [Confidentiality Controls](confidentiality-controls.md)
- [Privacy Controls](privacy-controls.md)
- [Security Policy](../security-policy.md)
- [Incident Response Plan](../incident-response-plan.md)

---

## 6. Auditor Selection

- Engage a CPA firm experienced in SOC 2
- Type I: Point-in-time design assessment
- Type II: Operating effectiveness over a period (typically 6–12 months)
- Expect annual audits for ongoing compliance
