# SOC 2 Availability Controls (A1 Series)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Availability criteria apply when you commit to system availability. Replace placeholders with your SLAs and procedures.

---

## 1. Availability Overview

The **Availability** Trust Service Criteria focus on system availability for operation and use per agreed-upon requirements. Include this criteria if you have uptime SLAs or availability commitments to customers.

---

## 2. A1.1 – Availability
The entity maintains, monitors, and evaluates current processing capacity and use of system components to manage capacity demand and to enable the implementation of planned capacity increases.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Capacity monitoring | CPU, memory, disk, network monitored | [Monitoring dashboards, thresholds] |
| Capacity planning | Forecast based on growth | [Capacity plan, review dates] |
| Scaling | Procedures for scaling up | [Runbook, cloud auto-scaling config] |

### 2.1 Capacity Planning Processes

Document how capacity is planned and reviewed:

| Process | Description | Frequency | Evidence |
|---------|-------------|----------|----------|
| Capacity review | Assess current utilization and trends | [MONTHLY/QUARTERLY] | [Review meeting notes, reports] |
| Demand forecast | Project growth (users, data, transactions) | [QUARTERLY] | [Forecast document] |
| Capacity triggers | Thresholds that trigger scaling or procurement | [Defined in runbook] | [Runbook, alert config] |
| Plan updates | Update capacity plan based on changes | [As needed; review ANNUALLY] | [Capacity plan version history] |

---

## 3. A1.2 – Environmental Protections
The entity authorizes, designs, develops or acquires, implements, operates, approves, maintains, and monitors environmental protections, software, data backup processes, and recovery infrastructure to meet its objectives.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Environmental | Physical protections (if on-prem) | [Data center controls; or cloud provider SOC] |
| Backup | Backup procedures, schedule, testing | [Backup config, restore test records] |
| Recovery | DR procedures, RTO/RPO defined | [BCP, DR runbooks] |

**Sourdough mapping:** Backup feature (Configuration > Backup). See [Business Continuity Plan](../business-continuity-plan.md).

---

## 4. SLA Documentation Template

Document your availability commitments:

```
Service: [APPLICATION_NAME]
Availability Target: [99.9%] uptime
Measurement Period: Monthly
Exclusions: Scheduled maintenance, customer-caused outages, force majeure

Scheduled Maintenance:
- Window: [e.g., Sundays 02:00–06:00 UTC]
- Notice: [NUMBER] hours advance notice
- Frequency: [WEEKLY/MONTHLY/AS_NEEDED]

Credits (if applicable): [LINK_TO_SLA_CREDIT_POLICY]
```

---

## 5. Monitoring and Alerting

| Metric | Threshold | Alert | Response |
|--------|------------|-------|----------|
| Uptime | < 99.9% monthly | [Weekly report] | [Review root cause] |
| Response time | > [NUMBER] ms p95 | [Page on-call] | [Investigate, scale] |
| Error rate | > [NUMBER]% | [Page on-call] | [Incident response] |
| Disk/DB | > 80% utilization | [Alert] | [Scale or clean] |

---

## 6. Incident and Outage Management

- Define severity levels for availability incidents
- Escalation path for outages
- Customer communication procedure
- Post-incident review for significant outages
- Track downtime for SLA reporting

See [Incident Response Plan](../incident-response-plan.md) for incident procedures.

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
