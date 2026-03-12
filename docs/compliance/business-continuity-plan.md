# Business Continuity Plan

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]  
**Document Owner:** [BCP_CONTACT_NAME]

> **Customize**: Replace bracketed placeholders. Adapt recovery objectives and procedures to your business requirements.

---

## 1. Overview

This Business Continuity Plan (BCP) ensures [COMPANY_NAME] can recover critical operations following disruptive events such as hardware failure, data corruption, natural disaster, or cyber incident.

---

## 2. Business Impact Analysis

### 2.1 Critical Systems

| System | Criticality | Max Downtime (RTO) | Max Data Loss (RPO) | Dependencies |
|--------|-------------|--------------------|---------------------|--------------|
| [Application Name] | [Critical/High/Medium] | [HOURS] | [HOURS] | Database, storage, auth |
| [Database] | [Critical] | [HOURS] | [MINUTES] | Storage, backups |
| [Other] | | | | |

> **Customize**: Complete the table for your critical systems. RTO = Recovery Time Objective; RPO = Recovery Point Objective.

### 2.2 Impact Assessment

| Downtime | Business Impact |
|----------|-----------------|
| < 1 hour | Minimal; acceptable for maintenance |
| 1–4 hours | Moderate; customer complaints, SLA risk |
| 4–24 hours | Significant; revenue impact, reputation risk |
| > 24 hours | Severe; contract breach, regulatory implications |

---

## 3. Recovery Objectives

### 3.1 Recovery Time Objective (RTO)
- **Target**: Restore critical systems within [NUMBER] hours
- **Maximum acceptable downtime**: [NUMBER] hours

### 3.2 Recovery Point Objective (RPO)
- **Target**: Lose no more than [NUMBER] hours/minutes of data
- **Backup frequency**: [HOURLY/DAILY/WEEKLY]

> **Customize**: Align with SLA commitments to customers.

---

## 4. Backup Procedures

### 4.1 Sourdough Backup Capabilities
Sourdough includes built-in backup functionality:

- **Location**: Configuration > Backup
- **What's backed up**: Database, storage files, configuration
- **Destination**: Local, S3, or other configured storage
- **Scheduling**: Via cron or manual trigger

> **Customize**: Document your backup schedule, retention period, and storage locations. See [docs/backup.md](../backup.md) for Sourdough backup documentation.

### 4.2 Backup Verification
- Test restores: [WEEKLY/MONTHLY]
- Backup integrity checks: [Describe]
- Offsite/offline copy: [Yes/No - describe]

### 4.3 Backup Retention
| Backup Type | Retention | Storage Location |
|-------------|-----------|------------------|
| Daily | [NUMBER] days | [LOCATION] |
| Weekly | [NUMBER] weeks | [LOCATION] |
| Monthly | [NUMBER] months | [LOCATION] |

---

## 5. Disaster Recovery Procedures

### 5.1 Scenario: Database Corruption/Loss
1. Identify last known-good backup
2. Restore database from backup to staging environment
3. Verify data integrity
4. Restore to production during maintenance window
5. Validate application functionality

### 5.2 Scenario: Full System Failure
1. Provision new infrastructure (or failover to standby)
2. Deploy application from version control
3. Restore database from backup
4. Restore file storage from backup
5. Update DNS/configurations
6. Verify and monitor

### 5.3 Scenario: Ransomware/Cyber Incident
1. Follow Incident Response Plan
2. Isolate affected systems
3. Do not pay ransom without legal consultation
4. Restore from known-good backups (pre-incident)
5. Rebuild systems with enhanced security
6. Conduct post-incident review

---

## 6. Roles and Responsibilities

| Role | Responsibility |
|------|----------------|
| **BCP Owner** | Plan maintenance, exercise coordination |
| **IT/DevOps** | Technical recovery execution |
| **Communications** | Stakeholder notification |
| **Management** | Declare disaster, approve resources |

---

## 7. Testing and Maintenance

### 7.1 Test Schedule
- **Tabletop exercise**: [QUARTERLY/ANNUALLY]
- **Technical recovery test**: [SEMI-ANNUALLY/ANNUALLY]
- **Full DR drill**: [ANNUALLY]

### 7.2 Plan Review
- This plan is reviewed at least [ANNUALLY]
- Updates triggered by: organizational changes, system changes, post-incident lessons learned

---

## 8. Contact Information

| Role | Contact |
|------|---------|
| BCP Owner | [NAME] - [EMAIL] |
| Primary IT | [NAME] - [EMAIL] |
| Hosting/Cloud Provider | [SUPPORT_LINK] |
| Key Vendors | [LIST] |

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
