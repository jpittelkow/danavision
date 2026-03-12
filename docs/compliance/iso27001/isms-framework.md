# ISO 27001 ISMS Framework Template

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: This template supports ISO 27001:2022. Replace placeholders and complete each section for your ISMS. Consult a certification body for formal requirements.

---

## 1. Information Security Policy (Template)

### 1.1 Policy Statement
[COMPANY_NAME] is committed to protecting the confidentiality, integrity, and availability of information assets. This policy establishes the framework for the Information Security Management System (ISMS) and applies to all personnel, processes, and systems within scope.

### 1.2 Scope
**In scope:**
- [List systems, processes, locations, departments]
- [Example: Web application, customer data, internal IT]

**Out of scope:**
- [List any exclusions with justification]

### 1.3 Objectives
- Protect information assets from unauthorized access, modification, or destruction
- Ensure compliance with applicable legal and contractual requirements
- Maintain and improve the ISMS through continual improvement
- Support business objectives while managing information security risks

### 1.4 Approval
Approved by: [NAME, TITLE]  
Date: [DATE]  
Review date: [DATE + 1 year]

---

## 2. Risk Assessment Methodology

### 2.1 Approach
- **Risk identification**: Assets, threats, vulnerabilities
- **Risk analysis**: Likelihood and impact
- **Risk evaluation**: Compare against risk criteria
- **Risk treatment**: Mitigate, accept, transfer, avoid

### 2.2 Risk Criteria

**Likelihood Scale (1–5):**
| Level | Description |
|-------|-------------|
| 1 | Rare |
| 2 | Unlikely |
| 3 | Possible |
| 4 | Likely |
| 5 | Almost certain |

**Impact Scale (1–5):**
| Level | Description |
|-------|-------------|
| 1 | Negligible |
| 2 | Minor |
| 3 | Moderate |
| 4 | Major |
| 5 | Severe |

**Risk Matrix:** Risk = Likelihood × Impact. Thresholds:
- **High** (15–25): Must treat
- **Medium** (5–12): Should treat
- **Low** (1–4): Accept or treat based on cost-benefit

### 2.3 Risk Assessment Template
| Asset | Threat | Vulnerability | Likelihood | Impact | Risk Score | Treatment |
|-------|--------|---------------|------------|--------|------------|-----------|
| [Asset] | [Threat] | [Vuln] | [1–5] | [1–5] | [L×I] | [Mitigate/Accept/etc.] |

---

## 3. Risk Treatment Plan Template

| Risk ID | Description | Treatment | Responsible | Due Date | Status |
|---------|-------------|-----------|-------------|----------|--------|
| R001 | [Risk description] | Mitigate: [Control] | [Name] | [Date] | [Open/Done] |
| R002 | | Accept: [Justification] | | | |

**Treatment options:**
- **Mitigate**: Implement controls to reduce likelihood or impact
- **Accept**: Accept residual risk with documented justification
- **Transfer**: e.g., insurance, contracts
- **Avoid**: Discontinue the activity causing the risk

---

## 4. Statement of Applicability (SoA) Structure

The SoA lists all Annex A controls and indicates whether each is applicable and how it is implemented.

| Control ID | Control Name | Applicable (Y/N) | Justification | Implementation |
|------------|--------------|------------------|---------------|----------------|
| A.5.1 | Policies for information security | Y | Required | [Security Policy], review annual |
| A.5.2 | Information security roles | Y | Required | [RACI, org chart] |
| A.8.1 | User endpoint devices | N | No company devices | N/A |
| … | | | | |

### Key Applicable Controls (Examples)

| Control | Implementation Reference |
|---------|---------------------------|
| A.5.1–5.4 | Organizational policies |
| A.8.1–8.34 | Technological controls |
| A.9.1–9.4 | Access control (RBAC, MFA, reviews) |
| A.10.1–10.2 | Cryptography |
| A.12.1–12.7 | Operations (backup, logging, change) |
| A.13.1–13.2 | Communications security |

> **Customize**: Complete full SoA with all 93 controls. Document "N/A" with justification for excluded controls.

---

## 5. Competence and Awareness

### 5.1 Competence
- Identify necessary competencies for roles
- Provide training or hire to meet competencies
- Evaluate effectiveness

### 5.2 Awareness
- Security awareness training for all personnel
- Frequency: [ANNUALLY] at minimum
- Topics: Policy, threats, reporting, acceptable use
- Records: Completion dates, attendance

---

## 6. Internal Audit

- **Frequency**: [ANNUALLY] at minimum
- **Scope**: ISMS and controls
- **Independence**: Auditors independent of area audited
- **Documentation**: Audit plan, reports, nonconformities, corrective actions

---

## 7. Management Review

- **Frequency**: [ANNUALLY] or when significant changes occur
- **Input**: Audit results, incidents, feedback, risk status, improvements
- **Output**: Decisions, actions, resource needs
- **Documentation**: Meeting minutes, action items

---

## 8. Continual Improvement

- Address nonconformities and incidents
- Track corrective and preventive actions
- Update risk assessment when context changes
- Annual policy and SoA review

---

## 9. Security Objectives

Define measurable security objectives aligned with business and ISMS goals. Review and update at least annually.

| Objective | Target | Measure | Owner | Review Date |
|-----------|--------|---------|-------|-------------|
| Reduce unauthorized access incidents | Zero critical access incidents | Incident count | [Security] | [DATE] |
| Maintain patch compliance | [e.g., 95%] of systems patched within [NUMBER] days | Patch compliance report | [IT] | [DATE] |
| Security awareness completion | 100% of staff complete training | Training records | [HR/Security] | [DATE] |
| Backup success rate | [e.g., 99%] successful backups | Backup logs | [IT] | [DATE] |
| Vulnerability remediation | Critical vulnerabilities remediated within [NUMBER] days | Scan results, remediation log | [Security] | [DATE] |

> **Customize**: Add or change objectives to match your organization. Ensure they are specific, measurable, and achievable.

---

## 10. Security Metrics

Track key performance indicators (KPIs) to monitor control effectiveness and support management review.

| Metric | Definition | Target | Frequency |
|--------|-------------|--------|------------|
| Mean time to detect (MTTD) | Time from incident occurrence to detection | [e.g., < 24 hours] | Per incident |
| Mean time to respond (MTTR) | Time from detection to containment/resolution | [e.g., < 4 hours critical] | Per incident |
| Failed login rate | Failed authentication attempts / total attempts | [Baseline, alert on spike] | [WEEKLY] |
| Access review completion | % of access reviews completed by due date | [e.g., 100%] | Per review cycle |
| Open high/critical vulnerabilities | Count of open high/critical vulns | [e.g., 0] | [WEEKLY] |
| Security training completion rate | % of staff completing annual training | 100% | [ANNUAL] |

> **Customize**: Align metrics with your Security Objectives and available data. Use audit logs, access logs, and scan results where applicable. Sourdough audit and access logs (Configuration > Logs) can support incident and access metrics.

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
