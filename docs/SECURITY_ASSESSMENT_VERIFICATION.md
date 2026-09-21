# MediStock RHU Security Assessment – Verification Register

**Purpose:** Trace important claims in the final assessment to repository evidence. Statuses mean:

- **Verified** – direct source/configuration/migration evidence was reviewed.
- **Partial** – evidence exists, but a material gap, limitation, or unconfigured dependency remains.
- **Not found** – no implementation evidence was found in the reviewed repository.
- **Proposed** – recommendation only; not a claim about current deployment.

| Claim | Status | Evidence reviewed | Notes |
|---|---|---|---|
| The project uses Laravel 12 and PHP 8.2. | Verified | `composer.json` | Framework/application stack. |
| Barcode support exists. | Verified | `SearchController`, scanner Blade view, medicine barcode view, `package.json` | Barcode lookup accepts barcode/internal code. USB keyboard-style scanner behavior is implemented in page JavaScript. |
| A camera-based scanner is active. | Not found | Scanner page review | Do not claim camera scanning merely because a barcode package is installed. |
| Current configuration is local development using MySQL. | Verified | Safe configuration review and `config/database.php` | No secret values are recorded here. Local development is normal; it is not called an automatic vulnerability. |
| The present deployment is Hybrid Cloud. | Not found / incorrect | Repository lacks cloud deployment/IaC evidence | Removed from final report. Proposed AWS deployment is **Public Cloud**. |
| FEFO is implemented. | Verified | `MedicineBatch::scopeAvailableFefo()`, `InventoryService::stockOut()` | Filters active/non-expired batches with positive quantity; orders by expiration then receipt date. |
| Expired stock is blocked from FEFO release. | Verified | `availableFefo()` and `InventoryService::stockOut()` | Uses `expiration_date >= today()` and active status. |
| Concurrent stock operations are guarded. | Verified | `InventoryService` | Receipt, release, and adjustment use `DB::transaction()` and `lockForUpdate()`. |
| Database constraints support integrity. | Verified | medicine-batch and inventory-transaction migrations | Foreign keys, unique transaction code, unique medicine+batch pair, and indexes are present. |
| Audit logging exists. | Verified | `AuditService`, audit migration, protected audit route | Records user, action, target, old/new values when supplied, IP, user agent, and timestamps. |
| Role-based permissions are implemented. | Verified | `routes/web.php`, `EnsurePermission`, `EnsureAdministrator`, role/user models and seeders | Protected groups use permission or administrator middleware; inactive accounts are denied in those middleware. |
| Login, registration, and password-reset routes are rate limited. | Partial | `routes/web.php` | Login, registration, and reset-email routes have throttle middleware; OTP verify routes were not observed with explicit route throttles. |
| Registration requires a strong password and email OTP. | Verified | `RegisterController`, `OtpService` | Password rule requires length/mixed case/numbers/symbols; OTP is 6 digits, hashed in cache, has 10-minute life, 5 attempts, 60-second resend cooldown. |
| MFA is implemented for every login. | Not found | Auth controllers and routes | OTP is used in registration/Google registration flow, not as a mandatory second factor after normal login. |
| Google OAuth is currently usable locally. | Partial | `GoogleAuthController`, safe config review | Routes and allowlist logic exist, but local Google client configuration is unset. |
| Normal user passwords are hashed. | Verified | `RegisterController`, `PasswordResetController`, `ProfileController`, admin reset flow | These flows call `Hash::make()`. |
| Password storage is fully secure. | Partial | `PlaintextUserProvider`, `config/auth.php`, demo seeder | Custom provider supports plaintext fallback; demonstration seed writes an unhashed password. This is a high-priority remediation. |
| Session fixation is addressed on login/logout. | Verified | `AuthController` | Successful login regenerates session; logout invalidates session and regenerates CSRF token. |
| Production HTTPS-only cookies are verified. | Not found | `config/session.php`, local config review | `secure` depends on env and was not explicitly set locally. Required for production deployment. |
| Session data is encrypted at rest. | Partial | `config/session.php`, safe configuration review | Session encryption is configurable but the final report avoids claiming it is enabled; production needs explicit verification. |
| Scanner lookup is permission-protected at route level. | Partial | `routes/web.php`, `SearchController` | Scanner routes are under `auth`; write actions check permissions in controller. Lookup route lacks a route-level `medicines.view` requirement and should be tightened. |
| Dashboard/notification endpoints recheck account active status. | Partial | `routes/web.php`, middleware | They use `auth`; active status is rechecked in permission/admin middleware, not in these routes. |
| Recipient/patient name may be stored for dispensing. | Verified | transaction migration, `InventoryController`, `InventoryService`, transaction model | Required only for `dispensed` type in stock-out form/controller. No clinical-record module was found. |
| Recipient names are field-level encrypted. | Not found | models/migrations/config reviewed | Treat as a proposed privacy enhancement after requirements analysis. |
| CSV export exists. | Verified | `ReportController`, report routes | Uses `streamDownload()` and `fputcsv()`. |
| CSV formula-injection protection exists. | Not found | `ReportController::export()` | Proposed remediation: neutralize text cells beginning with `=`, `+`, `-`, or `@`. |
| Cloud backups, WAF, KMS, monitoring, and infrastructure-as-code are active. | Not found | Repository review | These are recommendations only. |
| `.env` is excluded from Git. | Verified | `.gitignore` | `.env` and related environment files are ignored. No secret is reproduced in this register. |
| Public Cloud on AWS is a suitable future deployment model. | Proposed | Architecture analysis and AWS official documentation | Use PaaS for app hosting and DBaaS for MySQL; not a statement of current hosting. |
| AWS is solely responsible for application security. | Incorrect | AWS shared-responsibility documentation | AWS secures the cloud infrastructure; the RHU/project team still secures application code, identities, configuration, and data. |

## Evidence limitations

- This was a repository and safe local-configuration review, not a penetration test or a production infrastructure audit.
- No cloud account, server, backup target, mail provider, or external identity provider was accessed.
- Secret values, passwords, tokens, API keys, and connection strings were intentionally not collected or recorded.
- A source feature is not treated as a deployed control until its production configuration and operation can be evidenced.
