# RPMS client revision audit

Source: all 13 pages, including screenshots, of `RPMS_SYSTEM-ITO-LANG-LAHAT-NATATNDAAN-KO-HAHAH.pdf`. Reviewed 2026-09-28 before application changes. The pasted request governs execution; the PDF supplies product requirements. Screenshot-only password recovery on page 13 is included.

## Baseline and implementation checklist

| Pages | Requirement / roles | Initial gap | Affected implementation / data | Validation and dependencies |
|---|---|---|---|---|
| 1,8 | Green #2E7D32, orange #F57C00, cream #FFF8E7, white; real market logo; plain white RPMS on login/home | [UI/UX CHANGE] Brown admin/collector theme and filtered logo; wrong absolute asset paths | navigation, auth, index, shared styles; existing logo asset | Responsive navigation, legible loading screen and logo |
| 1,3,5,7,8,9,12,13 | Remove vendors, collectors, obsolete rules, payments/receipts and dashboard activity | [BUG] Hard deletes fail on payment foreign keys; several actions absent; unsafe GET writes | users/vendors/payments archive columns; approval, vendor, history, receipt, rule and activity UI | Preserve historical references; POST + CSRF; collector may only remove own collections; no unsolicited bulk purge |
| 2,6,10 | Working Excel-compatible and PDF exports; filters at top; preview before print | [BUG] Date mismatch, end-date exclusion, penalty omitted, invalid grouped rows | reports/export/payments queries | Filter parity, empty exports, escaping, CSV formula protection, explicit print action |
| 2,8 | Reports grouped together; remove Audit Trail | [NEEDS REVISION] Reports spread through Monitoring/Vendors, audit visible | admin navigation, dashboard, audit route | Keep internal historical records; remove audit interface rather than erase logs |
| 3 | Search collector performance and click transaction count to see vendor/stall/section | [MISSING] Count is plain text | collector_performance, payments | Collector/date/search filters and accurate totals |
| 4,5,8 | Vendor-owned uploads, one row per vendor; vendor/stall/section/status/search; document type/file/uploaded/view/print | [NEEDS REVISION] Flat admin list; registration extension-only validation; no subsequent vendor upload | registration, vendor documents, protected download, vendor_documents | MIME, size, failed uploads, ownership, replacement, review, protected storage |
| 5,6,7,11 | Monthly and daily rents; manual stall numbers repeated across sections; search section with natural stall order; scalable pages | [DATABASE CHANGE] No daily_rent; global duplicate validation; global auto-number | vendors, registration, POS, vendor lists | Nonnegative rent; duplicate active stall within same section only; 1,000-row pagination |
| 7 | Vendor payment history view only | [NEEDS REVISION] Print actions remain in AJAX/receipt | vendor history, receipt | Owner ID mapping; no print control or automatic printing |
| 8 | Vendor/collector self-registration, admin approval, vendor document submission | [NEEDS REVISION] Basic workflow exists; active session status not rechecked, missing fullname, false successful uploads | users, vendors, registration, approvals, all protected routes | Pending/inactive/archived accounts denied on direct routes; approval atomic |
| 8 | Day 1–5 5% discount, 6–20 regular, 21–month-end 20% penalty; automatic calendar | [IMPLEMENTED] Pure helper matches boundaries; [BUG] Other handlers trust client adjustments and legacy rules conflict | payment_rules, payment handlers, calendar, penalty settings, reports | Backend source of truth; February/leap/30/31-day boundaries; stored adjustments shown consistently |
| 8,12 | POS receipt shows discount and penalty; preview before print | [NEEDS REVISION] Receipt exists but late status starts day 6 and other print paths auto-print | collector/admin receipts and history | Both adjustment amounts and net displayed; no immediate print |
| 9 | Vendor account names visible; Recent Payments links to payments | [BUG] Blank vendor_name without fallback; payments.php is duplicate documents page | vendor_accounts, payments, dashboard | Existing unnamed vendor records still display linked account name |
| 10,11 | Remove nonworking language controls; dashboard remains complete on return | [NEEDS REVISION] Language switcher and missing summary endpoint; duplicate POS implementations | collector navbar/dashboard/POS | Full-page navigation; dashboard widgets and summary remain visible |
| 10,11 | Stall lookup lists all matching stalls across sections; daily/monthly only | [BUG] fetch() discards first match; dashboard still weekly; conflicting lookup paths | collector_payments, dashboard, collector_api | Zero/one/multiple matches, explicit vendor selection, daily rent and server quote |
| 11 | Vendor ranking search/filter; add stall/section | [MISSING] Unbounded ranking without stall/section | collector dashboard queries/table | Name/stall/section search and pagination |
| 12 | Correct overdue status; filters vendor/section/stall/account status/payment status | [BUG] Lifetime sums and no-payment dates cause immediate overdue | collector vendor list, overdue lists | Only positive balance with past explicit due date is overdue; partial/paid/current period |
| 13 | Password recovery for every role; three failed logins then 15-second cooldown | [MISSING] No reset/cooldown | auth, login attempts, reset tokens, mailer | Server enforcement, hashed one-use expiring tokens, generic reset reply, invalidation and CSRF |

## Decisions and limits

- Removal archives records and hides them from current lists. It does not erase financial history or silently clear existing data.
- Daily rent is an explicit amount, not an invented monthly/30 rule. Existing records require administrator configuration before daily collection.
- Existing weekly historical payments remain readable; new POS submissions allow daily/monthly only.
- Browser Print / Save as PDF is a preview followed by an explicit print action; CSV is Excel-compatible and labeled accordingly.
- Reuse the supplied market image asset; verify visually rather than invent a new logo.
- Application schema was inspected through local PDO. It contains no daily rent, archive fields, reset tokens, login throttle, or document review state. A repeatable additive migration is required.
- No Git repository is present. Validation must use a local backup, lint, isolated database fixtures, HTTP checks, and browser checks if a browser is available.

Final verification results are recorded separately after implementation; this is the pre-change audit, not a completion claim.
