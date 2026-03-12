# ISO 27001 Compliance Guide

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: ISO 27001 is an international standard for information security management systems (ISMS). Replace placeholders and adapt to your scope and certification goals.

---

## 1. ISO 27001 Overview

ISO/IEC 27001:2022 specifies requirements for establishing, implementing, maintaining, and continually improving an Information Security Management System (ISMS).

### Key Concepts
- **Risk-based approach**: Identify risks, apply controls, treat residual risk
- **Annex A**: 93 controls in 4 themes (Organizational, People, Physical, Technological)
- **Certification**: Third-party audit; certificate valid for 3 years with annual surveillance

---

## 2. Annex A Control Themes (2022)

| Theme | Controls | Examples |
|-------|----------|----------|
| **Organizational** | 37 | Policies, asset management, access control, cryptography |
| **People** | 8 | Screening, awareness, disciplinary |
| **Physical** | 14 | Secure areas, equipment, disposal |
| **Technological** | 34 | Network security, malware, backup, logging |

---

## 3. Certification Process

### 3.1 Typical Timeline
1. **Gap assessment** (1–2 months): Current state vs. requirements
2. **Implementation** (3–6+ months): ISMS and controls
3. **Stage 1 audit** (documentation): Review of ISMS documentation
4. **Stage 2 audit** (implementation): On-site/remote assessment of controls
5. **Certification**: Issuance of certificate
6. **Surveillance audits**: Annual audits during 3-year cycle

### 3.2 Documentation Required
- Information security policy
- Risk assessment methodology
- Risk assessment report
- Risk treatment plan
- Statement of Applicability (SoA)
- Competence and awareness records
- Internal audit reports
- Management review records

---

## 4. Sourdough Mapping to Annex A

| Control Area | Sourdough Relevance |
|--------------|---------------------|
| A.5 – Organizational controls | Policies, roles, asset management |
| A.8 – Technological controls | Access control, authentication, cryptography |
| A.9 – Access control | RBAC, user groups, MFA |
| A.10 – Cryptography | HTTPS, Laravel encryption |
| A.12 – Operations security | Logging, backup, change management |
| A.13 – Communications security | Network security, TLS |

See [ISMS Framework](isms-framework.md) for detailed templates.

---

## 5. Related Documents

- [ISMS Framework](isms-framework.md) – Policy, risk assessment, SoA, security objectives and metrics
- [Cryptographic Controls (A.10)](cryptographic-controls.md) – Encryption policy, key management
- [Operations Security (A.12)](operations-security.md) – Operational procedures, backup, logging, vulnerability management
- [Communications Security (A.13)](communications-security.md) – Network security, information transfer
- [Supplier Relationships (A.15)](supplier-relationships.md) – Third-party security requirements, vendor assessment
- [Security Policy](../security-policy.md)
- [Access Control Policy](../access-control-policy.md)
- [Data Handling Policy](../data-handling-policy.md)
