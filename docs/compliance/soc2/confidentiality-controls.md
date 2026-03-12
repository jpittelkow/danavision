# SOC 2 Confidentiality Controls (C1 Series)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Confidentiality criteria address the protection of confidential information. Identify your confidential information and map controls to classification, handling, and disposal.

---

## 1. Confidentiality Overview

The **Confidentiality** Trust Service Criteria address the entity's collection, use, retention, disclosure, and disposal of confidential information. Include this criteria when you commit to protecting information designated as confidential (e.g., non-public business or customer data).

---

## 2. C1.1 – Confidential Information Identification

The entity identifies and maintains confidential information in accordance with its objectives.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Classification | Confidential information is identified and classified | [Data classification policy, asset inventory] |
| Labeling | Classification labels or handling instructions | [Metadata, handling guidelines] |
| Inventory | Inventory of confidential information assets | [Asset register, data map] |

**Sourdough mapping:** Data classification and handling are documented in [Data Handling Policy](../data-handling-policy.md). Identify which data in your deployment is confidential and document in that policy.

---

## 3. C1.2 – Confidential Information Disposal

The entity disposes of confidential information to meet the entity's objectives.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Disposal procedures | Secure disposal when no longer needed | [Retention schedule, disposal procedures] |
| Disposal methods | Shredding, secure erase, or equivalent | [Procedure docs, vendor certs] |
| Verification | Disposal is verified and documented | [Disposal logs, certificates] |

**Sourdough mapping:** Data retention and disposal are covered in [Data Handling Policy](../data-handling-policy.md). Soft deletes and backup retention settings should align with disposal procedures. Backup encryption supports secure handling until disposal.

---

## 4. Confidentiality and Encryption

| Control | Implementation | Evidence |
|---------|----------------|----------|
| At rest | Encryption for confidential data at rest | [DB encryption, storage encryption] |
| In transit | TLS for data in transit | [HTTPS, TLS config] |
| Access control | Access limited to authorized persons | [Access Control Policy](../access-control-policy.md), RBAC |

**Sourdough mapping:** HTTPS enforced; Laravel encryption for sensitive stored data; access controlled via user groups and authentication.

---

## 5. Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
