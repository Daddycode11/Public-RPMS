"""Integration checks on synthetic records only; see tests/fixture.php."""
from http_smoke import *
import base64
import hashlib
import json
import os
from pathlib import Path
import subprocess
import uuid

checks = 0
def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print('PASS', label)

def sql(query):
    encoded = base64.b64encode(query.encode()).decode()
    source = f"<?php putenv('RPMS_DB_NAME=rpms_revision_test'); require 'config/database.php'; $q=$pdo->query(base64_decode('{encoded}')); echo json_encode($q->columnCount()?$q->fetchAll(PDO::FETCH_ASSOC):[]);"
    run = subprocess.run([r'C:\xampp\php\php.exe'], input=source, text=True, capture_output=True, check=True)
    return json.loads(run.stdout)

def post(op, path, fields):
    _, html, _ = request(op, path)
    return request(op, path, dict(csrf_token=token(html), **fields))

def upload(op, path, fields, files):
    boundary = 'RPMS' + uuid.uuid4().hex
    body = bytearray()
    for key, value in fields.items():
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    for key, filename, content in files:
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode())
        body.extend(content)
        body.extend(b'\r\n')
    body.extend(f'--{boundary}--\r\n'.encode())
    req = urllib.request.Request(BASE+path, data=bytes(body), headers={'Content-Type': 'multipart/form-data; boundary='+boundary})
    try:
        r = op.open(req)
        return r.status, r.read().decode(), r.url
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.url

if __name__ == '__main__':
    admin, collector, vendor = login('admin',1), login('collector',2), login('vendor',3)
    check(request(client(),'admin/vendors.php')[0]==403, 'Unauthenticated direct URL denied')
    check(request(vendor,'admin/vendors.php')[0]==403, 'Vendor cannot enter admin routes')
    check(request(collector,'admin/payments.php')[0]==403, 'Collector cannot enter admin routes')
    check(request(collector,'collector/collector_api.php',{'vendor_id':1})[0]==403, 'Missing CSRF rejected')
    check(request(admin,'admin/vendors.php?search[]=bad')[0]==400, 'Malformed array input rejected')
    _,html,_=request(collector,'collector/collector_payments.php?search=V-01')
    check('Dry Goods Vendor' in html and 'Fish Vendor' in html, 'Duplicate stalls list all sections')
    _,html,_=request(collector,'collector/collector_payments.php?vendor_id=1&payment_type=daily')
    key=re.search(r'name="request_key" value="([^"]+)"',html).group(1)
    fields=dict(csrf_token=token(html),request_key=key,vendor_id=1,payment_type='daily',discount=999,penalty=999,amount_paid=1)
    status,receipt,url=request(collector,'collector/collector_payments.php',fields)
    check(status==200 and 'collector_receipt.php?id=' in url, 'POS redirects to receipt preview')
    payment_id=int(url.split('id=')[-1])
    row=sql(f'SELECT amount_paid,discount,penalty,payment_date,paid_at FROM payments WHERE id={payment_id}')[0]
    day=int(row['payment_date'][-2:])
    check(float(row['amount_paid'])==50 and float(row['penalty'])==(10 if day>=21 else 0) and float(row['discount'])==(2.5 if day<=5 else 0), 'Server ignores forged amount/adjustments and uses configured rent')
    check(row['payment_date']==row['paid_at'][:10], 'Payment timestamps agree')
    request(collector,'collector/collector_payments.php',fields)
    check(len(sql(f"SELECT id FROM payments WHERE request_key='{key}'"))==1, 'Repeated submission is idempotent')
    other=login('collector',7)
    check(request(other,f'collector/collector_receipt.php?id={payment_id}')[0]==404, 'Collector cannot read another collector receipt')
    check(request(login('vendor',4),f'vendor/vendor_receipt.php?id={payment_id}')[0]==404, 'Vendor receipt ownership enforced')
    check('onclick="window.print()"' not in request(vendor,f'vendor/vendor_receipt.php?id={payment_id}')[1], 'Vendor receipt is view only')
    check(request(collector,'collector/collector_api.php',dict(fields,payment_type='weekly',request_key='a'*64))[0]==422, 'New weekly payments rejected')
    status,csv,_=request(admin,'admin/reports.php?format=csv&from='+row['payment_date']+'&to='+row['payment_date'])
    check(status==200 and 'Dry Goods Vendor' in csv and ',Penalty,' in csv, 'CSV includes end date and net penalty')
    check('Print / Save as PDF' in request(admin,'admin/reports.php?format=html_pdf')[1], 'PDF preview has explicit print action')
    check('Amount' in request(admin,'admin/reports.php?format=csv&from=1900-01-01&to=1900-01-01')[1] or 'Base amount' in request(admin,'admin/reports.php?format=csv&from=1900-01-01&to=1900-01-01')[1], 'Empty export retains headers')
    post(other,'collector/collector_history.php',dict(action='remove_payment',id=payment_id))
    check(sql(f'SELECT deleted_at FROM payments WHERE id={payment_id}')[0]['deleted_at'] is None, 'Other collector cannot archive payment')
    post(collector,'collector/collector_history.php',dict(action='remove_payment',id=payment_id))
    check(sql(f'SELECT deleted_at FROM payments WHERE id={payment_id}')[0]['deleted_at'] is not None, 'Owner can archive payment without deleting it')
    post(admin,'admin/collector_approvals.php',dict(action='deactivate',id=7))
    check(request(other,'collector/dashboard.php')[0]==403, 'Deactivation invalidates existing access')
    post(admin,'admin/collector_approvals.php',dict(action='approve',id=7))
    check(request(other,'collector/dashboard.php')[0]==200, 'Approved account access restored')
    # Upload actual PNG, reject mismatched MIME, enforce owner scope.
    _,html,_=request(vendor,'vendor/documents.php')
    status,html,_=upload(vendor,'vendor/documents.php',dict(csrf_token=token(html),action='upload',document_type='ID'), [('document','id.png',Path('assets/favicon/favicon-32x32.png').read_bytes())])
    check('Document submitted for review.' in html,'Vendor document upload succeeds')
    doc=sql('SELECT id,file_path FROM vendor_documents WHERE vendor_id=1 ORDER BY id DESC LIMIT 1')[0]
    check(request(login('vendor',4),f"vendor/download_document.php?id={doc['id']}")[0]==404,'Other vendor cannot download document')
    _,html,_=request(vendor,'vendor/documents.php')
    _,html,_=upload(vendor,'vendor/documents.php',dict(csrf_token=token(html),action='upload',document_type='ID'), [('document','fake.png',b'<?php echo 1;')])
    check('does not match' in html,'Disguised executable upload rejected')
    post(admin,f"admin/vendor_documents.php?vendor_id=1",dict(action='review',id=doc['id'],review_status='approved'))
    check(sql(f"SELECT review_status FROM vendor_documents WHERE id={doc['id']}")[0]['review_status']=='approved','Admin document review persists')
    # Register a new vendor and approve it without changing existing records.
    reg=client(); _,html,_=request(reg,'auth/register.php')
    reg_token=re.findall(r'name="csrf_token" value="([^"]+)"',html)[-1]
    email='registration-'+uuid.uuid4().hex[:10]+'@example.test'
    form=dict(csrf_token=reg_token,account_type='vendor',first_name='New',last_name='Vendor',email=email,password=PASSWORD,confirm_password=PASSWORD,vendor_name='Registration Vendor',stall_number='V-NEW-'+uuid.uuid4().hex[:6],section_id=1,contact='09000000000')
    _,html,_=upload(reg,'auth/register.php',form,[('documents[]','permit.png',Path('assets/favicon/favicon-32x32.png').read_bytes())])
    check('Registration submitted.' in html,'Vendor self-registration with documents succeeds')
    new=sql(f"SELECT id,status FROM users WHERE email='{email}'")[0]
    check(new['status']=='pending','New vendor requires approval')
    _,html,_=request(reg,'auth/login.php')
    _,html,url=request(reg,'auth/login.php',dict(csrf_token=token(html),email=email,password=PASSWORD,role='vendor'))
    check('pending admin approval' in html and '/auth/login.php' in url,'Pending vendor cannot log in')
    post(admin,'admin/collector_approvals.php',dict(action='approve',id=new['id']))
    _,html,_=request(reg,'auth/login.php')
    _,html,url=request(reg,'auth/login.php',dict(csrf_token=token(html),email=email,password=PASSWORD,role='vendor'))
    check('/vendor/dashboard.php' in url,'Approved new vendor can log in')
    # A hashed, expiring reset token is one use and invalidates existing sessions.
    reset=uuid.uuid4().hex+uuid.uuid4().hex
    hashed=hashlib.sha256(reset.encode()).hexdigest()
    sql(f"INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(7,'{hashed}',DATE_ADD(NOW(),INTERVAL 30 MINUTE))")
    resetting=client(); _,html,_=request(resetting,'auth/reset_password.php?token='+reset)
    fields=dict(csrf_token=token(html),token=reset,password=PASSWORD,confirm_password=PASSWORD)
    _,html,_=request(resetting,'auth/reset_password.php',fields)
    check('Password changed.' in html,'Valid reset token changes password')
    _,html,_=request(resetting,'auth/reset_password.php',fields)
    check('already used' in html,'Reset token cannot be replayed')
    check(request(other,'collector/dashboard.php')[0]==403,'Password reset revokes prior session')
    # Cooldown persists beyond the session cookie.
    failing=client()
    for _ in range(3):
        _,html,_=request(failing,'auth/login.php')
        _,html,_=request(failing,'auth/login.php',dict(csrf_token=token(html),email='collector2@example.test',password='wrong',role='collector'))
    check('Wait 15 seconds' in html,'Three failed logins trigger cooldown')
    fresh=client(); _,html,_=request(fresh,'auth/login.php')
    _,html,url=request(fresh,'auth/login.php',dict(csrf_token=token(html),email='collector2@example.test',password=PASSWORD,role='collector'))
    check('Too many attempts' in html and '/auth/login.php' in url,'Cooldown cannot be bypassed with a fresh session')
    # Cleanup only synthetic throttling; avoids delaying subsequent browser tests.
    sql('DELETE FROM login_attempts')
    post(admin,'admin/vendors.php',dict(action='remove_vendor',id=2))
    check(sql('SELECT deleted_at FROM vendors WHERE id=2')[0]['deleted_at'] is not None,'Vendor removal archives instead of breaking foreign keys')
    request(collector,'auth/logout.php')
    check(request(collector,'collector/dashboard.php')[0]==403,'Logout ends protected access')
    print(f'{checks} workflow checks passed')
