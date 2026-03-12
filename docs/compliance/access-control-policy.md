# Access Control Policy

**Organization:** [COMPANY_NAME]  
**Version:** 1.0  
**Last Updated:** [DATE]  
**Document Owner:** [IT_OR_SECURITY_CONTACT]

> **Customize**: Replace bracketed placeholders. Adapt procedures to your organizational structure and tools.

---

## 1. Purpose and Scope

This policy defines how [COMPANY_NAME] manages user access to systems and data. It applies to all applications, infrastructure, and data repositories.

---

## 2. Access Control Principles

### 2.1 Least Privilege
Users receive only the minimum access required to perform their job functions.

### 2.2 Need-to-Know
Access to sensitive data is restricted to those with a documented business need.

### 2.3 Separation of Duties
Critical functions are divided among multiple roles to prevent fraud and error.

---

## 3. User Provisioning

### 3.1 New User Request
1. Manager submits access request with justification
2. [IT/Security] verifies identity and role
3. Access granted per role template
4. User acknowledges acceptable use policy
5. Access documented in [identity system / spreadsheet]

### 3.2 Role Assignment
- Roles defined in [Sourdough user groups / LDAP / IdP]
- Default role for new users: [ROLE_NAME]
- Admin role requires [additional approval / MFA]

> **Customize**: Sourdough uses group-based RBAC. Users belong to groups (e.g., Admin, User); groups determine permissions. See Configuration > User Groups.

---

## 4. User Deprovisioning

### 4.1 Triggers
- Employment termination
- Role change
- Extended leave ([NUMBER] days or more)
- Security incident (immediate revocation)

### 4.2 Procedure
1. [HR/Manager] notifies [IT/Security] of termination or change
2. Access revoked within [NUMBER] hours of notification
3. For termination: disable account, revoke sessions, revoke API tokens
4. For role change: adjust permissions per new role
5. Verify no orphaned access; document completion

### 4.3 Sourdough-Specific
- Revoke user in Configuration > Users (or equivalent)
- User groups automatically enforce permission changes
- Sanctum tokens expire per `SANCTUM_TOKEN_EXPIRATION`; force logout if needed

---

## 5. Role-Based Access Control (RBAC)

### 5.1 Role Definitions

| Role | Permissions | Who |
|------|-------------|-----|
| **Admin** | Full configuration, user management, logs, backups | [IT, designated admins] |
| **User** | Dashboard, own profile, assigned features | Standard users |
| **Read-Only** | View only; no changes | [Auditors, support] |

> **Customize**: Map to your Sourdough groups and any custom permissions. Sourdough implements Admin vs. User via the Admin group.

### 5.2 Privileged Access Management

Privileged access (e.g., Admin group, root, elevated system access) poses higher risk and requires additional controls.

#### 5.2.1 Admin Account Management
- Admin accounts are assigned to named individuals; no shared admin accounts for day-to-day use.
- Admin role assignment requires [manager/security] approval and is documented.
- Admin accounts are used only when elevated access is needed; use standard accounts for routine work.
- Sourdough: Admin group membership is managed in Configuration > User Groups; changes are audited.

#### 5.2.2 Elevated Access Procedures
- Request for privileged access must be justified and approved per [procedure].
- Access is time-bound where possible (e.g., project duration, temporary admin).
- Privileged access is revoked when no longer required (role change, project end).

#### 5.2.3 Privileged Access Monitoring
- Privileged actions are logged (audit trail); logs are retained per [Data Handling Policy](data-handling-policy.md).
- [IT/Security] reviews privileged access activity [MONTHLY/QUARTERLY] for anomalies.
- Sourdough: Audit logs (Configuration > Logs) record user actions; access logs (when enabled) support access tracking.

#### 5.2.4 Emergency Access ("Break Glass")
- Procedures for emergency access when normal approval path is unavailable (e.g., incident response).
- Emergency access is requested, used, and revoked per [documented procedure]; all use is logged and reviewed.
- Post-use review to confirm appropriateness and update procedures if needed.

---

## 6. Access Review Procedures

### 6.1 Frequency
- **User access**: Reviewed [QUARTERLY/SEMI-ANNUALLY]
- **Privileged access**: Reviewed [MONTHLY/QUARTERLY]
- **Service accounts**: Reviewed [QUARTERLY]

### 6.2 Process
1. Generate access report (users, roles, last login)
2. Managers confirm or revoke access per report
3. [IT/Security] implements changes
4. Document review completion and exceptions

---

## 7. Authentication Requirements

- Strong passwords per [Security Policy](security-policy.md)
- MFA for: [All users / Admins / Remote access]
- Session timeout: [NUMBER] minutes
- Failed login lockout: [NUMBER] attempts, [NUMBER] minutes

---

## 8. Third-Party and Service Account Access

- Third-party access only with contract and security assessment
- Service accounts: minimal permissions; no interactive login where possible
- Credentials rotated [QUARTERLY] or per policy
- Document all service accounts and owners

---

## 9. Audit and Compliance

- Access grants and revocations logged
- Failed access attempts monitored
- Sourdough audit logs: Configuration > Logs
- Retain access logs per [Data Handling Policy](data-handling-policy.md)

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | [DATE] | [AUTHOR] | Initial version |
