"""Run only against the synthetic rpms_revision_test database on port 8098."""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://127.0.0.1:8098/'
PASSWORD = 'Revision-Test-Password-2026!'

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(op, path, data=None):
    if isinstance(data, dict):
        data = urllib.parse.urlencode(data).encode()
    try:
        r = op.open(BASE + path, data)
        return r.status, r.read().decode('utf-8'), r.url
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8'), e.url

def token(html):
    match = re.search(r'name="csrf_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
    return match.group(1)

def login(role, ident):
    op = client()
    _, html, _ = request(op, 'auth/login.php')
    status, html, url = request(op, 'auth/login.php', dict(csrf_token=token(html), email=f'{role}{ident}@example.test', password=PASSWORD, role=role))
    assert status == 200 and f'/{role}/dashboard.php' in url, (role, status, html[:500])
    return op

ROUTES = {
    'admin': ['dashboard.php','vendors.php','collector_approvals.php','reports.php','payments.php','export.php','collector_performance.php','vendor_documents.php','payment_calendar.php','penalty_settings.php','sections.php','partial_payments.php','late_payments.php','collect_payment.php','stall_map.php','collector_assignments.php','maintenance_requests.php','announcements.php','profile.php'],
    'collector': ['dashboard.php','collector_payments.php?search=V-01','collector_history.php','collector_receipt.php','vendors_list.php','collector_today_summary.php','collector_profile.php','collector_announcements.php'],
    'vendor': ['dashboard.php','vendor_payment_history.php','documents.php','vendor_account.php','vendor_maintenance.php','vendor_announcements.php'],
}
if __name__ == '__main__':
    failures = []
    count = 0
    for role, ident in [('admin',1),('collector',2),('vendor',3)]:
        op = login(role, ident)
        for route in ROUTES[role]:
            status, html, _ = request(op, role + '/' + route)
            errors = re.findall(r'(?:Fatal error|Warning|Deprecated|Notice)(?:</b>)?:.*?(?:<br|\n)', html, re.S)
            if status != 200 or errors:
                failures.append((role+'/'+route,status,errors[:2] or html[:300]))
            count += 1
    for failure in failures:
        print(failure)
    print(f'HTTP smoke: {count-len(failures)}/{count} pages passed')
    raise SystemExit(bool(failures))
