# Security Incident Response Plan

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]  
**Document Owner:** [SECURITY_CONTACT_NAME]

> **Customize**: Replace bracketed placeholders. Adapt severity levels and procedures to your organization's size and risk profile.

---

## 1. Overview

This plan defines procedures for detecting, containing, and recovering from security incidents affecting [COMPANY_NAME] systems and data.

---

## 2. Incident Classification

### 2.1 Severity Levels

| Severity | Definition | Examples | Response Time |
|----------|------------|----------|---------------|
| **P1 - Critical** | Active breach, data exfiltration, or system-wide outage | Ransomware, credential theft, production database compromise | Immediate |
| **P2 - High** | Significant impact, potential data exposure | Unauthorized access attempt, malware detected, DDoS | < 1 hour |
| **P3 - Medium** | Limited impact, contained incident | Phishing attempt, policy violation, failed intrusion | < 4 hours |
| **P4 - Low** | Minor impact, informational | Scan detected, outdated software, minor misconfiguration | < 24 hours |

> **Customize**: Adjust definitions and response times based on your SLAs and risk tolerance.

---

## 3. Response Team Roles and Contacts

| Role | Name | Contact | Backup |
|------|------|---------|--------|
| Incident Commander | [NAME] | [EMAIL/PHONE] | [NAME] |
| Technical Lead | [NAME] | [EMAIL/PHONE] | [NAME] |
| Communications Lead | [NAME] | [EMAIL/PHONE] | [NAME] |
| Legal/Compliance | [NAME] | [EMAIL/PHONE] | [NAME] |

**24/7 On-Call:** [PHONE_NUMBER_OR_PAGERDUTY]

---

## 4. Detection and Reporting

### 4.1 Detection Sources
- Audit logs and access logs (Sourdough: Configuration > Logs)
- Security monitoring alerts
- User reports to [CONTACT_EMAIL]
- Third-party notifications (hosting provider, vendors)

### 4.2 Reporting Procedure
1. Report immediately to [CONTACT_EMAIL] or [ON_CALL_NUMBER]
2. Include: What was observed, when, by whom, systems affected
3. Do not attempt to contain or remediate without Incident Commander approval (unless critical)
4. Preserve evidence: do not power off systems or delete logs without instruction

---

## 5. Response Procedures

### 5.1 Phase 1: Identification
- Confirm incident is real (rule out false positives)
- Classify severity (P1–P4)
- Activate response team for P1/P2
- Document: incident ID, start time, initial assessment

### 5.2 Phase 2: Containment
- Short-term: Isolate affected systems, block malicious IPs, revoke compromised credentials
- Long-term: Apply patches, segment network, enhance monitoring
- Preserve forensic evidence before making changes

### 5.3 Phase 3: Eradication
- Remove malware, close vulnerabilities, delete unauthorized accounts
- Verify no persistent access remains

### 5.4 Phase 4: Recovery
- Restore systems from known-good backups if needed
- Re-enable services gradually with enhanced monitoring
- Verify normal operation

### 5.5 Phase 5: Post-Incident Review
- Conduct within [NUMBER] days of incident closure
- Document: timeline, root cause, lessons learned, improvements
- Update this plan and related procedures as needed

---

## 6. Communication Templates

### 6.1 Internal Notification (P1/P2)
```
Subject: [SECURITY INCIDENT] P[1|2] - [Brief Description]

A security incident has been identified. Severity: P[1|2].

Current status: [Containment in progress / Under investigation]
Impact: [Description]
Actions: [What is being done]

Next update: [TIME]
Contact: [Incident Commander]
```

### 6.2 Customer Notification (if data breach)
- Coordinate with Legal before external communication
- Follow regulatory requirements (GDPR 72-hour notification, etc.)
- Template: [LINK_TO_CUSTOMER_NOTIFICATION_TEMPLATE]

---

## 7. Evidence Preservation

- Capture system logs, network traffic, disk images before changes
- Document all actions taken with timestamps
- Maintain chain of custody for legal proceedings
- Store evidence securely with access controls

---

## 8. Integration with Audit Logging

Sourdough provides audit logs and access logs that support incident investigation:

- **Audit logs**: User actions, settings changes, admin operations
- **Access logs**: HIPAA-style access tracking for PHI (when enabled)
- **Location**: Configuration > Logs in the admin interface

> **Customize**: Document how your audit logs integrate with SIEM or other monitoring tools.

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
