# ISO 27001 Cryptographic Controls (A.10)

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]

> **Customize**: Define your policy on use of cryptographic controls and key management. Replace placeholders with your algorithms, key lengths, and procedures.

---

## 1. Overview

ISO 27001 Annex A.10 addresses the use of cryptography to protect confidentiality, integrity, and availability of information. This document provides a template for policy and key management procedures.

---

## 2. A.10.1.1 – Policy on Use of Cryptographic Controls

A policy on the use of cryptographic controls for the protection of information shall be developed and implemented.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Policy statement | Documented policy on when and how cryptography is used | [This policy, approval record] |
| Algorithm standards | Approved algorithms and key lengths | [Table below] |
| Use cases | Encryption for data at rest, in transit, passwords, etc. | [Policy section 3] |

### 2.1 Approved Algorithms (Template)

| Use Case | Algorithm / Standard | Key Length / Parameters | Notes |
|----------|----------------------|-------------------------|-------|
| Data in transit | TLS | 1.2 or 1.3 | Disable weak ciphers |
| Passwords (hashing) | bcrypt / Argon2 | [Per implementation] | No plaintext storage |
| Data at rest (application) | AES or framework default | [e.g., 256-bit] | [Laravel: APP_KEY] |
| Data at rest (storage) | [Vendor/disk encryption] | [As per vendor] | [Cloud provider or LUKS] |
| API tokens / session | Cryptographically secure generation | [Per implementation] | [Sanctum, session driver] |

> **Customize**: Align with your jurisdiction and standards (e.g., NIST, local regulations). Replace with your approved algorithms.

### 2.2 Sourdough Mapping

| Control | Sourdough Implementation |
|---------|---------------------------|
| Data in transit | HTTPS enforced; TLS termination at nginx/reverse proxy |
| Passwords | Laravel bcrypt; compromised password check in production |
| Application encryption | Laravel encryption (APP_KEY) for sensitive stored data |
| Tokens | Sanctum API tokens; cryptographically secure generation |
| Session | Laravel session driver (cookie/session encryption) |

---

## 3. A.10.1.2 – Key Management

Rules for the effective use of cryptography, including key management, shall be defined and implemented.

### Implementation

| Control | Implementation | Evidence |
|---------|----------------|----------|
| Key generation | Cryptographically secure generation | [Documented procedure] |
| Key storage | Keys protected (e.g., env, HSM, secrets manager) | [Storage documentation] |
| Key distribution | Secure distribution and access control | [Access policy] |
| Key rotation | Rotation schedule and procedure | [Rotation schedule] |
| Key compromise | Procedure if key is compromised | [Incident response, rotation] |
| Key disposal | Secure disposal when keys are no longer needed | [Disposal procedure] |

### 3.1 Key Management Template

| Key Type | Storage | Rotation | Owner |
|----------|---------|----------|-------|
| APP_KEY (Laravel) | Environment / secrets manager; not in code | [Annual or on compromise] | [IT/Security] |
| TLS certificates | Server or load balancer | Per CA validity (e.g., annual) | [IT] |
| Database credentials | Environment / secrets manager | [Per policy] | [IT] |
| Third-party API keys | Environment / secrets manager | [Per policy or vendor] | [Application owner] |

> **Customize**: Document each key type you use. Do not commit keys to version control. Use [.env.example](.env.example) only for variable names, not values.

### 3.2 Sourdough Mapping

- **APP_KEY**: Used for Laravel encryption and signed cookies. Stored in `.env` or deployment secrets; must be kept secret and rotated if compromised.
- **Database and external secrets**: Stored in environment; rotation requires application or config redeploy.
- **TLS**: Handled at reverse proxy/load balancer; certificate renewal per your infrastructure.

---

## 4. Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
