# Data Handling Policy

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]  
**Document Owner:** [DATA_PROTECTION_OFFICER_OR_EQUIVALENT]

> **Customize**: Replace bracketed placeholders. Adapt classification levels and retention periods to your data types and regulatory requirements (GDPR, CCPA, HIPAA, etc.).

---

## 1. Purpose and Scope

This policy defines how [COMPANY_NAME] classifies, handles, stores, and disposes of data. It applies to all personnel and systems that process organizational or customer data.

---

## 2. Data Classification Levels

### 2.1 Classification Definitions

| Level | Definition | Examples |
|-------|------------|----------|
| **Confidential** | Highly sensitive; unauthorized disclosure causes severe harm | Passwords, API keys, financial records, health data |
| **Internal** | Business-sensitive; disclosure causes moderate harm | Internal documents, unreleased features, strategic plans |
| **Public** | No restriction; safe for public disclosure | Marketing materials, public documentation |

> **Customize**: Add or rename levels (e.g., Restricted, Limited) to match your organization. Consider PHI, PII, and PCI data categories.

### 2.2 Classification by Data Type

| Data Type | Default Classification | Handling Requirements |
|-----------|------------------------|------------------------|
| User credentials | Confidential | Encrypted at rest and in transit; access logged |
| Personal data (PII) | Confidential or Internal | Per privacy policy; access controls; retention limits |
| Application logs | Internal | Access restricted; retention [NUMBER] days |
| Audit logs | Confidential | Immutable; access restricted; retention per compliance |
| Configuration (non-secret) | Internal | Version controlled; access by role |

---

## 3. Handling Procedures by Classification

### 3.1 Confidential Data
- Encrypt at rest and in transit
- Access only on need-to-know basis; access logged
- Store in designated secure locations
- No transmission via unencrypted email or messaging
- Secure disposal (e.g., cryptographic wipe, secure delete)

### 3.2 Internal Data
- Access by authorized personnel
- Transmit via approved channels (encrypted preferred)
- Store in access-controlled systems
- Dispose per retention schedule

### 3.3 Public Data
- No special handling; verify before public release
- Ensure no confidential/internal data is included

---

## 4. Retention and Disposal

### 4.1 Retention Periods

| Data Type | Retention | Legal/Regulatory Basis |
|-----------|-----------|-------------------------|
| User account data | [NUMBER] years after account closure | [Contract/Regulation] |
| Audit logs | [NUMBER] years | SOC 2, ISO 27001 |
| Access logs (HIPAA) | 6 years | HIPAA if applicable |
| Application logs | [NUMBER] days | Operational |
| Backups | [NUMBER] days/weeks | Recovery |

> **Customize**: Set retention based on jurisdiction and industry. GDPR requires minimization; some regulations require minimum retention.

### 4.2 Disposal Procedures
- **Electronic**: Secure delete (overwrite) or cryptographic destruction
- **Backups**: Purge per retention; verify disposal
- **Physical**: Shred or certified destruction
- **Document**: Disposal date and method for audit

---

## 5. Privacy Considerations

### 5.1 Personal Data (PII)
- Collect only what is necessary
- Document lawful basis (consent, contract, legitimate interest)
- Provide privacy notice to data subjects
- Support data subject rights: access, rectification, erasure, portability, objection

### 5.2 GDPR (if applicable)
- Designate DPO if required
- Data Processing Agreement for processors
- Breach notification within 72 hours
- Data Protection Impact Assessment for high-risk processing

### 5.3 CCPA (if applicable)
- Disclose data collection and use
- Honor opt-out of sale (if applicable)
- Provide access and deletion upon request

### 5.4 HIPAA (if handling PHI)
- BAA with business associates
- Minimum necessary standard
- Access logging (Sourdough supports HIPAA-style access logs when enabled)

---

## 6. Sourdough-Specific Notes

- **Audit logs**: Configuration > Logs; supports compliance evidence
- **Access logs**: HIPAA-compliant access tracking when enabled in settings
- **Encryption**: Laravel encryption for sensitive settings; HTTPS for transit
- **Backup**: Backup settings in Configuration; ensure backup destination meets data handling requirements

---

## 7. Roles and Responsibilities

| Role | Responsibility |
|------|----------------|
| Data Owner | Classification, retention decisions |
| Data Custodian | Technical implementation of controls |
| All Personnel | Comply with handling procedures; report violations |

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
