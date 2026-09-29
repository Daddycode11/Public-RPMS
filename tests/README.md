# Isolated regression checks

Run from the repository root with XAMPP PHP and MariaDB available. Never point the HTTP test server at the application database. Fixtures contain only synthetic accounts and copy table structure, not production records.

1. Run the application migration if not already applied: `C:\xampp\php\php.exe migrations/20260928_client_revisions.php`.
2. With the normal database selected, run `C:\xampp\php\php.exe tests/fixture.php`. It refuses to overwrite an existing `rpms_revision_test` schema.
3. In a dedicated PowerShell terminal, create `tests/.sessions` and `tests/.uploads`, then set `$env:RPMS_DB_NAME='rpms_revision_test'` and `$env:RPMS_MAIL_ENABLED='0'`. Start PHP at `127.0.0.1:8099`, using absolute workspace paths for the `session.save_path` and `upload_tmp_dir` PHP settings and the repository root as document root.
4. Run in order: `python tests/http_smoke.py`, `python tests/workflows.py`, `python tests/extended_workflows.py`, `python tests/auth_edge_cases.py`. Run `C:\xampp\php\php.exe tests/payment_rules_test.php` for pure calendar checks.
5. Optional browser validation requires Chrome at the path in `browser_smoke.cjs` and Playwright installed with `npm install --prefix .tmp_browser playwright`. Run `node tests/browser_smoke.cjs`; screenshots are written to `tests/artifacts`.
6. Stop the dedicated server. With `$env:RPMS_DB_NAME='rpms_revision_test'`, run `C:\xampp\php\php.exe tests/teardown.php`. It verifies the schema name and synthetic administrator before deleting that schema and its uploads. Clear the environment override afterward.

The HTTP base URL can be overridden with `RPMS_TEST_URL`. Python tests use the fixed isolated schema for SQL assertions. They are intended for a fresh fixture in the stated sequence. Never reuse the synthetic passwords for actual accounts. Test routes are denied by the supplied Apache configuration.
