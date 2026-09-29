# RPMS PDF revision — implementation report

Completed against all 13 PDF pages and the pre-change [audit](pdf-revision-audit.md). Verification completed September 29, 2026. The pasted user request governed the work; the PDF supplied application requirements.

## Implemented

- Applied the requested green, orange, cream and white theme, supplied market logo, and white RPMS login/home treatment. Corrected asset paths and mobile header overflow.
- Added safe removal through archival for accounts, vendors, payments/receipts, legacy penalty rules and activity. Historical relationships remain intact. Collector removal is limited to their own collections.
- Grouped report navigation and removed the Audit Trail interface. Reports and histories have search/date filters, pagination, consistent net totals, genuine Excel XLSX exports, and PDF previews using explicit browser Print / Save as PDF. CSV export remains available internally. This supersedes the initial audit's CSV-only export approach.
- Added searchable collector performance and clickable transaction counts, showing vendor, stall and section in the resulting payment list.
- Added vendor-owned document uploads, replacement/removal, grouped administrator review, status/search, document details, authorized viewing/download and printable supported previews. Uploads validate size, MIME and document format; administrators review rather than upload for vendors.
- Added explicit daily and monthly rents, manual stall identifiers, same stall identifiers across different sections, duplicate checks within a section, natural stall ordering and paginated vendor lists.
- Completed vendor and collector registration/approval, pending-account restrictions, names from linked accounts, and session revocation after deactivation/password changes.
- Centralized server-calculated payments: days 1–5 receive 5% discount; days 6–20 are regular; days 21–month-end receive 20% penalty. Daily/monthly POS selection shows all matching stalls across sections. Confirmation prevents duplicate payment submission and receipts show base, adjustments and net.
- Kept vendor history view-only, corrected dashboard receipt/payment navigation, restored collector Dashboard navigation and summary, removed language controls, and added ranking search/pagination with stall and section.
- Corrected overdue classification to require positive balance and an explicit past due date; added account/payment status filters.
- Added password recovery with hashed expiring single-use tokens, persistent three-failure/15-second login cooldown, CSRF validation, ownership checks, and repaired administrator OTP verification/resend handling.
- Corrected related import, receipt verification, profile contact, date filtering, and payment timestamp inconsistencies encountered during the audit.

## Modified files

The changes span existing role pages plus shared implementations. Principal files are:

| Area | Files |
|---|---|
| Authentication | `auth/login.php`, `auth/register.php`, `auth/otp_verify.php`, `auth/forgot_password.php`, `auth/reset_password.php`, `includes/login_security.php`, `includes/mailer.php` |
| Shared authorization and UI | `includes/security.php`, `includes/protected.php`, `includes/page.php`, `assets/css/revisions.css`, `assets/js/revisions.js`, role `navbar.php` files, `index.php` |
| Payments, reports and receipts | `includes/payment_service.php`, `includes/payment_pos.php`, `includes/payment_records.php`, `includes/spreadsheet_export.php`, `includes/receipt_page.php`, admin/collector collection, report, history and receipt routes |
| Vendors and accounts | `includes/vendor_records.php`, `includes/account_approvals.php`, `includes/archive.php`, admin approval/vendor routes, collector vendor/overdue routes |
| Documents | `includes/documents.php`, `includes/document_page.php`, `includes/document_download.php`, admin/vendor document and download routes, `uploads/documents/.htaccess` |
| Dashboards and calendar | `admin/dashboard.php`, dashboard AJAX routes, `collector/dashboard.php`, `collector/collector_api.php`, `admin/collector_performance.php`, `admin/payment_calendar.php`, `includes/penalty_page.php` |
| Configuration and verification | `config/database.php`, `migrations/20260928_client_revisions.php`, `database_migration_registration.sql`, directory `.htaccess` protections, `tests/*`, this report and the audit |

Obsolete mutation routes were disabled or redirected to the authorized shared workflows. Development mail/setup entry points are CLI-only. An original-source backup remains locally because this workspace has no Git repository.

## Database changes

Applied `migrations/20260928_client_revisions.php` to the local application database. The migration is repeatable and additive:

- Archive fields for users, vendors, payments, documents, activity and legacy rules; user authentication version; nullable vendor daily rent; document review status; unique payment request key.
- New `login_attempts` and `password_resets` tables.
- Repaired missing AUTO_INCREMENT properties on 12 existing tables, including payments and sections. Existing IDs and row counts were checked before and after and preserved.

No production records were removed. Existing daily rents remain unset until an administrator enters actual values. Historical weekly payments remain readable, while new POS collections allow daily/monthly only.

## Testing performed

- PHP syntax: 99 application/test PHP files passed the final sweep; teardown was executed successfully afterward.
- Payment rules: 1,461 calendar dates covering ordinary years, leap year 2024 and non-leap century 2100, plus rounding cases.
- HTTP smoke checks: 33 authenticated role pages without PHP warnings or request failures.
- Integration: 71 workflow assertions across payment integrity/idempotency, role/record authorization, CSRF, approval/deactivation, document security, exports/imports, recovery, cooldown and OTP.
- Scale: 1,000 synthetic vendors; 50 pages and natural stall ordering verified.
- Browser: 13 routes at desktop/mobile widths; no recorded JavaScript errors or horizontal overflow. Navigation, duplicate-stall selection and ranking checked.

Tests used an isolated schema containing synthetic records and disabled outbound mail. Its database, uploaded test documents, local test servers, temporary libraries and screenshots were removed after verification. Test source remains under `tests/`; see [test instructions](../tests/README.md).

## Remaining setup and verification

- Enter actual daily rental amounts through administrator vendor editing before collecting daily rent.
- Configure the deployed `RPMS_BASE_URL` for recovery links and verify SMTP delivery for approval, recovery and OTP using the deployment's mail account. Live mail delivery was not tested.
- Verify Apache honors the supplied `.htaccess` access restrictions, especially uploaded documents and configuration directories. HTTP tests ran through PHP's development server, which does not apply Apache rules.
- PDF export uses the browser's preview and Save as PDF facility; it is not a server-generated downloadable PDF binary.
