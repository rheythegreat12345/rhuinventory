# MediStock RHU – Sudipen Rural Health Unit
# Cloud and Security Architecture Assessment

**Course activity:** PElec6 Midterm Laboratory / Application Capstone Project

**System assessed:** MediStock RHU – medicine inventory web application

**Assessment date:** September 9, 2026

**Scope note:** This report is a source-code and configuration review of the project supplied for the laboratory activity. It does not claim that the proposed cloud design is already deployed. No application code, database record, configuration value, or secret was changed while preparing this report.

---

## Assessment overview

MediStock RHU is a Laravel 12 and PHP 8.2 web application for tracking medicines, batches, stock receipts, releases or dispensing, adjustments, suppliers, expirations, notifications, reports, and audit history. Its current repository configuration is a **local development environment** using MySQL. A local development setup is normal during development; it is not, by itself, a production security failure.

This assessment separates statements into these labels:

- **Verified current implementation** – found in the source, routes, migrations, configuration, or tests.
- **Partial implementation** – the feature exists but has an important gap or depends on configuration not present locally.
- **Not found in repository** – no supporting implementation was found during this review.
- **Proposed recommendation** – a future control or cloud deployment step; it is not represented as already running.

The recommended deployment model is **Public Cloud**, not Hybrid Cloud. RHU workstations and barcode scanners are clients of the web application. They do not create a private cloud. A hybrid label would only be correct if a real private/on-premises cloud environment were connected to a public-cloud environment.

## 1. Current system profile

| Area | Current assessment |
|---|---|
| Application | **Verified current implementation:** Laravel 12 web application with PHP 8.2. |
| Database | **Verified current implementation:** MySQL is used by the local development configuration. |
| Core records | Medicines, medicine batches, suppliers, storage locations, inventory transactions, notifications, audit logs, users, roles, and permissions. |
| Main workflows | Receive stock, FEFO release/dispensing, stock adjustment, medicine management, barcode lookup, reports, expiration monitoring, and low-stock monitoring. |
| Current hosting | **Verified current implementation:** local development configuration. No cloud deployment files or infrastructure-as-code were found. |
| Scanner support | **Verified current implementation:** barcode lookup accepts barcode or internal medicine code; the scanner page supports keyboard-style USB scanner input and browser WebUSB-related behavior. A barcode rendering dependency is listed in `package.json`, but the reviewed scanner page also contains custom JavaScript barcode rendering. |
| Patient-related data | **Verified current implementation:** a recipient/patient-name field can be saved on a dispensing transaction. No diagnosis, prescription, or full clinical-record module was found. |

The system is an inventory application. It should not be presented as an electronic medical record or a clinical decision system.

## 2. Cloud deployment model

### 2.1 Recommended deployment model: Public Cloud

**Proposed recommendation:** Deploy the application and database to a public-cloud provider such as AWS. The public-cloud label is appropriate because the proposed application and database services would run in AWS, while the RHU PCs, browser clients, and barcode scanners simply connect through the internet.

### 2.2 Proposed service models

| Proposed component | Service model | Reason |
|---|---|---|
| Laravel application on AWS Elastic Beanstalk | PaaS | It can provision and manage the application environment, load balancing, health monitoring, and scaling while the project team still maintains the Laravel code and its security configuration. |
| MySQL on Amazon RDS | DBaaS | It provides a managed relational-database service; the RHU still owns data classification, access control, backup policy, and application behavior. |
| Object storage for exports/attachments, if added | Managed storage service | Use encrypted object storage with restricted access and a lifecycle policy. This is proposed only; no attachment-storage feature was verified in the repository. |

### 2.3 Proposed public-cloud architecture

```text
RHU PCs / browser clients / USB barcode scanners
                |
             HTTPS
                |
     [Proposed AWS WAF]
                |
 [Proposed Application Load Balancer]
                |
 [Proposed Laravel application – Elastic Beanstalk]
                |
        private database connection
                |
     [Proposed Amazon RDS for MySQL]
                |
    encrypted backups and controlled recovery
```

Optional proposed additions are CloudWatch logging/alarms, AWS Secrets Manager or Parameter Store for runtime secrets, AWS KMS for encryption-key management, and S3 for approved report/export storage. These are recommendations, not verified current features.

## 3. Shared security responsibilities

In a public-cloud deployment, security is shared. AWS protects the underlying cloud facilities, hardware, networking, and managed-service infrastructure. The RHU/project team remains responsible for users, passwords, permissions, Laravel and package updates, secure configuration, application data, backup/recovery testing, monitoring review, and incident response.

| Security area | Cloud provider responsibility | RHU/project responsibility |
|---|---|---|
| Physical data centers, host hardware, core networking | Provider | — |
| Managed platform/database infrastructure | Provider, according to the selected service | Configure services safely and limit access |
| Laravel code and dependencies | — | Patch, test, and deploy securely |
| Application roles and records | — | Apply least privilege and review access |
| Database data and backups | Managed service operation where selected | Classify data, control access, retain backups safely, and test restore procedures |
| HTTPS, WAF rules, security groups, logs | Provider supplies services | Enable, configure, monitor, and respond |

## 4. Asset analysis

| Asset | Classification | Why it matters | Current evidence / recommendation |
|---|---|---|---|
| Medicine catalog and batch data | Internal operational data | Incorrect values can cause stock or expiry errors | **Verified:** medicines and batches are stored with batch identifiers, quantity, expiration, supplier, and location relationships. |
| Inventory transactions | Sensitive operational data | Shows movement, user attribution, and purpose | **Verified:** transaction code, batch, user, quantity, recipient, purpose, and timestamps are stored. |
| Recipient/patient name for dispensing | Personal data | Must be limited to the minimum necessary | **Verified:** optional/required-for-dispensing recipient field exists. **Proposed:** minimize, restrict access, and set retention rules. |
| User accounts and roles | Confidential security data | Controls access to inventory actions | **Verified:** users have status and role relationships; roles have permission records. |
| Audit logs | Sensitive security evidence | Supports accountability and investigation | **Verified:** action, user, target, before/after values, IP address, user agent, and timestamps are recorded. |
| Passwords and reset tokens | Highly confidential | Account compromise can expose the system | **Partial:** normal registration/reset code hashes passwords, but a legacy plaintext password fallback exists; see Section 9. |
| Application secrets and mail/OAuth credentials | Highly confidential | Exposure can allow unauthorized access or service abuse | **Verified:** `.env` is ignored by Git. **Proposed:** store production secrets in a managed secret service, rotate them, and never place them in reports or source control. |
| Reports and exports | Internal / potentially sensitive | Can expose operational or personal data if shared carelessly | **Verified:** CSV, Excel-style, and print report exports exist. **Proposed:** access review, export logging, controlled sharing, and formula-injection protection. |

## 5. Role-based access control and least privilege

**Verified current implementation:** route groups use `permission` middleware for medicines, stock receiving, stock release, adjustments, transactions, reports, suppliers, categories, and analytics. Administrator-only pages use separate `administrator` middleware. Both middleware checks reject inactive users before allowing protected actions.

| Role | Verified intended access summary |
|---|---|
| Administrator | Full system access and configuration; administrator middleware protects accounts, roles, settings, and audit logs. |
| Pharmacist | Medicine, dispensing/stock operations, suppliers/categories, reports/export, and analytics according to seeded permissions. |
| Inventory Staff | Medicine and stock operations, transactions, and reports/export according to seeded permissions. |
| RHU Staff | Read access to medicine, transaction, and report information according to seeded permissions. |
| Viewer | Read-only inventory overview permissions according to seeded permissions. |

**Least-privilege recommendation:** keep the roles narrow, review role assignments regularly, disable inactive accounts promptly, and require a re-login or immediate session invalidation after account deactivation.

## 6. Data-flow security review

### 6.1 Context-level data flow (DFD Level 0)

```text
Authorized RHU user -> browser -> MediStock RHU application -> MySQL database
                                       |                         |
                                       v                         v
                              notifications / reports       audit logs

USB barcode scanner -> browser input -> barcode lookup -> medicine/stock workflow
```

### 6.2 Major workflow controls (DFD Level 1)

1. A user signs in through the Laravel authentication flow. Login routes are rate-limited, and the application regenerates the session ID after successful login.
2. The authorization middleware verifies active account status and the required permission for protected medicine, stock, report, and administration routes.
3. On stock receipt, the application validates the medicine, supplier, batch, quantity, cost, and non-expired expiration date. It uses a database transaction and row locking while updating a batch.
4. On stock release, the application queries active, non-expired batches ordered by earliest expiration then receipt date. It locks rows and blocks a requested quantity that exceeds non-expired stock.
5. The application creates inventory transactions and audit records. It also creates relevant inventory notifications.
6. Authorized report users can view or export filtered report data.

### 6.3 Data-flow risks and controls

| Risk | Current control | Status |
|---|---|---|
| Concurrent stock changes | Database transactions and `lockForUpdate()` are used in receipt, release, and adjustment services. | Verified |
| Dispensing expired stock | FEFO query filters active batches, positive quantity, and expiration date on/after today. | Verified |
| Over-release | Service compares requested quantity against summed eligible stock and rejects excess. | Verified |
| Unauthorized protected operations | Permission and administrator middleware protect most functional route groups. | Verified / partial; scanner routes need the improvement in Section 9. |
| Tampered form values | Laravel request validation and database foreign keys are used. | Verified |
| CSRF on normal web forms | Laravel web middleware and CSRF form tokens are used in the views. | Verified |

## 7. Encryption and transport security

| Control | Status | Evidence / assessment |
|---|---|---|
| Password hashing in normal registration and reset flows | Verified | The controllers call Laravel `Hash::make()` and OTP values are hashed before caching. |
| Password verification | Partial | The custom provider verifies hashes when present, but also accepts a stored non-hash using constant-time comparison. This should be removed after a one-time migration. |
| Local development transport | Verified local setup | The reviewed local configuration uses a local HTTP application URL. This is appropriate for local development only. |
| HTTPS in production | Proposed | Terminate TLS at the load balancer, redirect HTTP to HTTPS, and set secure cookie configuration in production. |
| Session storage | Verified / partial | Database-backed sessions are configured. Cookie `HttpOnly` defaults to true and SameSite defaults to Lax in `config/session.php`; session encryption and secure-cookie values must be explicitly reviewed and set for production. |
| Database-at-rest encryption | Not found for current local database | **Proposed:** use RDS encryption with KMS and encrypted backups. |
| Field-level encryption for recipient name | Not found | **Proposed:** assess whether the recipient field needs application-level encryption; at minimum, restrict access and avoid unnecessary personal data. |

## 8. Privacy and data-protection considerations

The app stores inventory information and may store the recipient/patient name for a dispensed medicine. The reviewed source did not show diagnoses, clinical notes, prescriptions, or a full patient chart. Even so, a name associated with a dispensing event is personal data and should be treated carefully.

Recommended privacy practices:

- collect only the recipient data required for the workflow;
- use a patient reference number instead of a full name when operationally acceptable;
- limit transaction and audit-log access to roles with a valid operational need;
- define retention, deletion, and backup-retention rules with the RHU and its data-protection officer;
- avoid putting personal data in report filenames, public links, screenshots, or chat messages;
- document the lawful purpose, access process, and breach-response procedure under the Philippine Data Privacy Act of 2012 and applicable RHU policy.

These privacy recommendations are **proposed**. A formal legal compliance determination is outside this code review.

## 9. Vulnerabilities, gaps, and mitigation plan

| ID | Finding | Status | Risk | Recommended mitigation |
|---|---|---|---|---|
| V1 | The configured user provider accepts a password hash when available but falls back to comparing a stored plaintext password. A demonstration seeder also writes an unhashed password. | Verified | High | Migrate every existing password to Laravel hashes, remove the plaintext provider/fallback, and never seed plaintext passwords in any deployable environment. |
| V2 | The scanner routes are inside `auth` middleware but are not all protected at the route level by `permission:medicines.view`. Controller checks protect write actions, but ordinary lookup can still reach a medicine redirect without an explicit view-permission check. | Verified / partial | Medium | Put scanner lookup/view routes under the correct view permission, enforce the same check in the controller, and test each role. |
| V3 | Dashboard and notification endpoints use `auth` but do not independently run the active-account middleware. A previously authenticated account that later becomes inactive may keep access to these endpoints until session invalidation/re-login. | Verified / partial | Medium | Add an active-user middleware to the whole authenticated group or invalidate that user’s sessions on deactivation. |
| V4 | CSV export writes report values directly through `fputcsv()` without visible spreadsheet-formula neutralization. | Verified / partial | Medium | Prefix spreadsheet-trigger characters (`=`, `+`, `-`, `@`) in exported text fields, document the choice, and add tests. |
| V5 | Current runtime is local development; production protections such as HTTPS enforcement, `APP_DEBUG=false`, explicit secure cookie settings, monitored backups, and network restriction are not verified in the repository. | Verified current state / proposed hardening | Medium for a public deployment | Keep local development local; before deployment, implement and verify the listed production controls. |
| V6 | Google OAuth routes exist, but local review found its client credentials unset. | Partial | Low to medium | Either configure the integration through a secure secret store and restrict allowed domains/emails, or remove/hide the option until it is operational. |
| V7 | No repository evidence of a tested backup/restore plan, disaster-recovery runbook, cloud monitoring policy, WAF configuration, or infrastructure-as-code. | Not found | Medium | Create these operational controls before cloud launch and test restoration on a schedule. |
| V8 | Recipient names are stored in transaction records, and audit payload design can retain broad record snapshots for some events. No field-level encryption was found. | Verified / partial | Medium privacy impact | Minimize stored personal data, use access controls, define retention, review audit payload contents, and consider field-level encryption after requirements analysis. |

The findings are based on repository evidence only. They do not claim an attack occurred or that an unreviewed deployed server is vulnerable.

## 10. Proposed secure public-cloud architecture

**Proposed only – not current implementation.**

1. Register a controlled domain and force HTTPS.
2. Place AWS WAF in front of an Application Load Balancer.
3. Run the Laravel application in an Elastic Beanstalk PHP environment in private subnets where practical.
4. Use Amazon RDS for MySQL without public database access. Permit database traffic only from the application security group.
5. Store runtime secrets in AWS Secrets Manager or Systems Manager Parameter Store; do not copy them into source control or reports.
6. Enable RDS encryption and encrypted backups; set retention and test a restore.
7. Send application and infrastructure logs to CloudWatch; set alarms for health failures, high error rates, failed logins, and unusual administrative activity.
8. Use least-privilege IAM roles and a separate non-root deployment account with MFA.
9. Configure `APP_ENV=production`, `APP_DEBUG=false`, HTTPS-only cookies, secure session settings, and a production mail service.
10. Apply a patching, dependency-review, backup, and incident-response schedule.

## 11. Technical implementation and validation plan

| Priority | Work item | Acceptance evidence |
|---|---|---|
| 1 | Remove plaintext password compatibility and rehash existing accounts safely. | Automated login/reset tests show hashed passwords only; no plaintext fallback remains. |
| 2 | Close scanner and inactive-session authorization gaps. | Role-based feature tests return 403 where expected and allow only assigned actions. |
| 3 | Add CSV spreadsheet-formula neutralization. | Tests export text beginning with formula characters safely. |
| 4 | Prepare production configuration. | Deployment checklist confirms debug disabled, HTTPS, secure cookies, non-root DB account, and restricted network paths. |
| 5 | Deploy a small staging environment. | Health check, authenticated smoke test, FEFO release test, and report export test pass. |
| 6 | Configure backup, restore, and monitoring. | Documented restore exercise and alert test are retained as evidence. |
| 7 | Review privacy and retention with RHU leadership/DPO. | Approved data inventory, retention policy, and access procedure. |

## 12. Technical supporting evidence

| Evidence area | Repository evidence reviewed |
|---|---|
| Stack | `composer.json`, `package.json`, `config/*`, `README.md` |
| Routes and authorization | `routes/web.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsurePermission.php`, `EnsureAdministrator.php` |
| Authentication | `AuthController`, registration, password-reset, Google-auth controllers, `OtpService`, `config/auth.php`, `config/session.php` |
| Inventory integrity | `InventoryController`, `InventoryService`, `Medicine`, `MedicineBatch`, inventory migrations |
| Auditability | `AuditService`, audit migration, administrator audit-log route |
| Scanner | `SearchController`, `resources/views/search/scanner.blade.php`, medicine barcode view, `package.json` |
| Reporting | `ReportController`, report routes and views |
| Database design | medicine batch, inventory transaction, audit log, role, user, notification migrations |
| Testing | Feature-test folders including inventory, medicine-management, and search-experience tests were present. |
| Secret handling | `.gitignore` excludes `.env`; environment values were not copied into this report. |

## Conclusion

MediStock RHU already has useful inventory controls: FEFO selection, non-expired-stock checks, row locking, database transactions, audit logging, role/permission middleware, account-approval logic, and validated stock workflows. These are good foundations for a capstone inventory application.

The most important improvement before any real public deployment is to remove plaintext-password compatibility and resolve the authorization and export-safety gaps identified above. The current system should be described honestly as a local Laravel/MySQL development application. A secure AWS **Public Cloud** deployment is recommended, but it remains a proposal until it is configured, tested, and documented.

## References

1. Laravel Documentation. *Laravel 12.x Documentation.* https://laravel.com/docs/12.x
2. Amazon Web Services. *What is AWS Elastic Beanstalk?* https://docs.aws.amazon.com/elasticbeanstalk/latest/dg/Welcome.html
3. Amazon Web Services. *Shared responsibility – AWS Well-Architected Security Pillar.* https://docs.aws.amazon.com/wellarchitected/latest/security-pillar/shared-responsibility.html
4. National Privacy Commission. *Data Privacy Act of 2012 (Republic Act No. 10173).* https://privacy.gov.ph/data-privacy-act/
5. National Privacy Commission. *Implementing Rules and Regulations of the Data Privacy Act of 2012.* https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/

---

## Appendix A – Input–Process–Output (IPO)

| Input | Process | Output |
|---|---|---|
| Medicine details, barcode, supplier, batch/lot, quantity, cost, expiration | Validate and create/update a medicine batch through a database transaction | Active batch, stock-in transaction, notification, audit entry |
| Medicine selection, quantity, purpose, optional recipient name | Select earliest eligible active/non-expired FEFO batches, lock rows, validate available quantity, deduct stock | One or more linked stock-out/dispensing transactions, updated batch quantities, audit entry |
| Adjustment quantity and reason | Lock selected batch, prevent negative result, save adjustment | Adjusted batch quantity, transaction, adjustment record, audit entry |
| Search/scanner value | Match barcode or internal medicine code, then route to allowed workflow | Medicine lookup, stock-in, restock, or dispensing workflow |
| Report type and filters | Query current inventory, batches, or transactions | Screen report, CSV, Excel-style export, or print view |

## Appendix B – Use-case summary

| Actor | Use cases |
|---|---|
| Administrator | Manage accounts/roles/settings, view audit logs, manage inventory, run reports |
| Pharmacist | View/manage medicines according to permission, receive/release stock, dispense, manage suppliers/categories, run reports |
| Inventory Staff | Manage permitted inventory activities, receive/release/adjust stock, view transactions, run reports |
| RHU Staff / Viewer | View the permitted inventory, transaction, and report information |
| Barcode scanner | Sends code as browser input; it is not an independent authenticated actor |

## Appendix C – DFD Level 1: stock release using FEFO

```text
Authorized user
  -> enters medicine, quantity, type, recipient (when dispensing), purpose
  -> Laravel validation
  -> InventoryService database transaction
  -> query eligible batches: active + non-expired + quantity > 0
  -> order by expiration then receipt date + lock rows
  -> validate total available quantity
  -> deduct one or more batches
  -> create transaction records + audit entry + update alerts
  -> return success/error response
```

## Appendix D – Screenshots

Capture the screenshots listed in [SCREENSHOTS_TO_CAPTURE.md](SCREENSHOTS_TO_CAPTURE.md) and insert them in the final submitted document. The screenshots are not included here because this review did not modify or capture the running system.
