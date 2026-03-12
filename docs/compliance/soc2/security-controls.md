# SOC 2 Security Controls (Common Criteria CC Series)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Map each control to your implementation. Document evidence locations and responsible parties.

---

## 1. Control Environment (CC1)

### CC1.1 – Control Environment
The organization demonstrates a commitment to integrity and ethical values.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Tone at top | Management communicates security importance | [Policy acknowledgments, training] |
| Structure | Security roles defined | [Org chart, RACI] |
| Authority | Delegation of authority documented | [Policy documents] |

---

## 2. Communication and Information (CC2)

### CC2.1 – Communication
Security information is communicated to support functioning of internal control.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Policies | Security policies published and accessible | [Policy repository, version control] |
| Training | Security awareness training | [Training records, completion rates] |
| Incidents | Incident reporting process | [Incident Response Plan] |

---

## 3. Risk Assessment (CC3)

### CC3.1 – Risk Assessment
The organization identifies and assesses risks to objectives.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Risk identification | Periodic risk assessment | [Risk register, assessment dates] |
| Risk analysis | Impact and likelihood evaluated | [Risk matrix, treatment plan] |
| Change management | New risks assessed for changes | [Change review process] |

---

## 4. Monitoring (CC4)

### CC4.1 – Monitoring
The organization monitors internal control.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Ongoing monitoring | Log review, alert response | [Log config, runbooks] |
| Evaluations | Periodic control assessments | [Assessment reports] |
| Deficiencies | Deficiencies communicated and remediated | [Issue tracking, remediation] |

---

## 5. Control Activities (CC5)

### CC5.1 – Control Activities
Control activities are selected and developed to mitigate risks.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Selection | Controls aligned to risks | [Control matrix] |
| Technology | Automated controls where appropriate | [SAST, monitoring tools] |
| Policies | Policies establish control activities | [Policy documents] |

---

## 6. Logical and Physical Access (CC6)

### CC6.1 – Logical Access
Logical access to systems is restricted and managed.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| User provisioning | Request, approval, provisioning process | [Access Control Policy] |
| User deprovisioning | Revocation upon termination | [Procedure, sample records] |
| Access reviews | Periodic access certification | [Review reports] |
| MFA | Multi-factor for sensitive access | [Config screenshots] |
| Password policy | Strong password requirements | [Security Policy] |

**Sourdough mapping:** User groups, 2FA, passkeys, Sanctum tokens. See [Access Control Policy](../access-control-policy.md).

### CC6.2 – Physical Access
Physical access to facilities and equipment is restricted.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Facility access | Badge, visitor log, etc. | [Describe for your environment] |
| Data center | If colo/on-prem, physical controls | [Vendor SOC reports if cloud] |

> **Customize**: If using cloud (AWS, GCP, Azure), reference provider's SOC 2 report for physical security.

---

## 7. System Operations (CC7)

### CC7.1 – Detection
Security events are detected and analyzed.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Logging | Audit logs, access logs, application logs | [Log config, sample logs] |
| Monitoring | Alerts for anomalies | [Alert rules, runbooks] |
| Vulnerability management | Scans, patching | [Scan results, patch records] |

**Sourdough mapping:** Audit logs, access logs (Configuration > Logs). SAST in CI. See [SDLC Policy](../sdlc-policy.md).

### CC7.2 – Response
Security incidents are responded to in a timely manner.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Incident response | Documented procedures | [Incident Response Plan] |
| Escalation | Defined escalation path | [Contact list, runbooks] |
| Post-incident | Review and improvement | [PIR templates] |

---

## 8. Change Management (CC8)

### CC8.1 – Change Management
Changes to infrastructure, data, and software are managed.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Approval | Changes require approval | [Change process, tickets] |
| Testing | Testing before production | [Test evidence, staging] |
| Deployment | Controlled deployment | [Deployment logs, rollback procedure] |

**Sourdough mapping:** Code review, CI/CD, security scan in pipeline.

---

## 9. Risk Mitigation (CC9)

### CC9.1 – Vendor Management
Vendor risks are assessed and managed.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Vendor assessment | Security review of vendors | [Questionnaire, SOC reports] |
| Contracts | Security requirements in contracts | [MSA, DPA templates] |
| Monitoring | Ongoing vendor monitoring | [Review schedule] |

### CC9.2 – Risk Mitigation
Risks from vendors and other parties are mitigated.

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Contracts | BAAs, DPAs where applicable | [Signed agreements] |
| Insurance | Cyber, E&O insurance | [Certificate of insurance] |

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
