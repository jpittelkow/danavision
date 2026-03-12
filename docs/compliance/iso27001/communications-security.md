# ISO 27001 Communications Security (A.13)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Document network security and information transfer policies. Replace placeholders with your network architecture and transfer procedures.

---

## 1. Overview

ISO 27001 Annex A.13 addresses network security management and the security of information transfer. This template supports compliance with technological controls for communications.

---

## 2. A.13.1 – Network Security Management

Networks shall be secured, managed, and controlled to protect information in systems and applications.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Network policy | Documented network security policy | [This document, network diagram] |
| Segmentation | Network segmentation where appropriate | [Network design, firewall rules] |
| Access control | Access to network services controlled | [Firewall, ACLs] |
| Monitoring | Network traffic monitored for anomalies | [IDS/IPS, logs] |

### 2.1 Sourdough Mapping

| Control | Sourdough Implementation |
|---------|---------------------------|
| HTTPS | TLS enforced; HTTP redirected to HTTPS |
| Security headers | nginx configured with X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, Content-Security-Policy |
| CORS | Restricted methods and headers; preflight cached. See `backend/config/cors.php` |
| Rate limiting | Auth-sensitive routes protected via `rate.sensitive` middleware |

Configuration references: `docker/nginx.conf`, `backend/config/cors.php`, Laravel middleware.

---

## 3. A.13.2 – Information Transfer

Rules for the transfer of information between organizations, and with external parties, shall be established and applied.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Transfer policy | Policy on transfer methods (e.g., encrypted only) | [Policy document] |
| Confidentiality | Confidential information encrypted in transit | [TLS, VPN, secure protocols] |
| Agreements | Transfer agreements with third parties where applicable | [DPA, contracts] |

### 3.1 Transfer Methods (Template)

| Method | Allowed | Conditions |
|--------|---------|------------|
| HTTPS/TLS | Yes | For web and API traffic |
| Email | [Yes/No] | [Encryption required for confidential data] |
| File transfer | [SFTP/secure only] | [Specify approved methods] |
| Physical media | [If allowed] | [Encryption, chain of custody] |

**Sourdough mapping:** All user-facing and API traffic over HTTPS. Outbound HTTP (e.g., webhooks, integrations) should use HTTPS; SSRF protection via `UrlValidationService` prevents access to internal/private URLs.

---

## 4. Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
