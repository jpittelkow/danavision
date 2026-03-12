# SOC 2 Privacy Controls (P1 Series)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Include Privacy criteria when you collect, use, retain, disclose, or dispose of personal information. Adapt to your privacy practices and applicable laws (GDPR, CCPA, etc.).

---

## 1. Privacy Overview

The **Privacy** Trust Service Criteria address the collection, use, retention, disclosure, and disposal of personal information. Include this criteria if you process personal data and have privacy commitments.

---

## 2. P1.1 – Notice and Choice
The entity provides notice about its privacy practices and provides choices about how personal information is used.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Privacy notice | Published privacy policy | [URL, version, date] |
| Notice at collection | Notice when data collected | [Screens, consent flows] |
| Choice | Opt-in/opt-out where required | [Preference center, consent records] |

---

## 3. P1.2 – Use and Retention
The entity limits the use and retention of personal information to the identified purposes.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Purpose limitation | Data used only for stated purposes | [Privacy policy, data map] |
| Retention | Retention schedule defined and followed | [Data Handling Policy] |
| Disposal | Secure disposal when no longer needed | [Disposal procedure] |

---

## 4. P1.3 – Access and Disclosure
The entity provides individuals access to their personal information and discloses it only for the identified purposes.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Access requests | Process for data subject access | [DSAR procedure, response template] |
| Disclosure controls | Disclosure only per policy/consent | [Third-party list, DPAs] |
| Minimization | Disclose only what is necessary | [Disclosure checklist] |

---

## 5. P1.4 – Monitoring and Enforcement
The entity monitors compliance with its privacy practices and has procedures to address inquiries and complaints.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Monitoring | Periodic privacy reviews | [Review schedule, reports] |
| Complaints | Process for privacy complaints | [Complaint form, response SLA] |
| Enforcement | Escalation, remediation | [Procedure document] |

---

## 6. Privacy Notice Template (Excerpt)

```
[COMPANY_NAME] Privacy Notice
Last Updated: [DATE]

1. Information We Collect
   We collect: [list categories - e.g., account info, usage data, cookies]

2. How We Use Your Information
   We use your information to: [purposes - e.g., provide service, improve, communicate]

3. Sharing and Disclosure
   We share with: [categories - e.g., service providers, legal requirements]
   We do not sell your personal information. [If applicable]

4. Data Retention
   We retain your data for [period] or as required by law.

5. Your Rights
   You may: access, correct, delete, export, opt out of marketing
   Contact: [PRIVACY_EMAIL]

6. Security
   We implement [describe measures] to protect your data.

7. Changes
   We will notify you of material changes via [method].
```

> **Customize**: Ensure compliance with GDPR, CCPA, and other applicable laws. Consult legal counsel.

---

## 7. Data Subject Access Request (DSAR) Process

1. **Receive** – Request via [email/form] to [PRIVACY_EMAIL]
2. **Verify** – Authenticate identity of requester
3. **Gather** – Collect data from [systems]
4. **Review** – Ensure no third-party data; redact if needed
5. **Respond** – Provide data within [30] days (GDPR) or per applicable law
6. **Document** – Log request, response, timeline

---

## 8. Sourdough Considerations

- User data stored in database; document what is collected
- Audit logs may contain PII; define retention and access
- Backup data may include personal data; secure backup destination
- SSO: document what attributes are received from IdP

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
