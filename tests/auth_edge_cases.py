from workflows import *

if __name__=='__main__':
    # This server has RPMS_MAIL_ENABLED=0, so tests cannot email real recipients.
    for role,ident in [('collector',5),('collector',6)]:
        op=client(); _,html,_=request(op,'auth/login.php')
        _,html,url=request(op,'auth/login.php',dict(csrf_token=token(html),email=f'{role}{ident}@example.test',password=PASSWORD,role=role))
        check('/auth/login.php' in url and ('pending admin approval' in html or 'deactivated' in html),'Pending/inactive login denied: '+str(ident))
    op=client(); _,html,_=request(op,'auth/register.php')
    csrf=re.findall(r'name="csrf_token" value="([^"]+)"',html)[-1]
    email='collector-register-'+uuid.uuid4().hex[:8]+'@example.test'
    _,html,_=request(op,'auth/register.php',dict(csrf_token=csrf,account_type='collector',first_name='New',last_name='Collector',email=email,password=PASSWORD,confirm_password=PASSWORD))
    check('Registration submitted.' in html and sql(f"SELECT status FROM users WHERE email='{email}'")[0]['status']=='pending','Collector self-registration remains pending')
    # Password-recovery HTTP flow stores only a token digest and gives generic replies.
    reset=client(); _,html,_=request(reset,'auth/forgot_password.php')
    _,html,_=request(reset,'auth/forgot_password.php',dict(csrf_token=token(html),email='collector2@example.test'))
    row=sql('SELECT token_hash,expires_at FROM password_resets WHERE user_id=2 ORDER BY id DESC LIMIT 1')[0]
    check('If the address belongs to an account' in html and len(row['token_hash'])==64,'Password recovery stores a digest and hides account existence')
    expired='e'*64
    sql("INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(2,'"+hashlib.sha256(expired.encode()).hexdigest()+"',DATE_SUB(NOW(),INTERVAL 1 MINUTE)) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at),used_at=NULL")
    _,html,_=request(reset,'auth/reset_password.php?token='+expired)
    _,html,_=request(reset,'auth/reset_password.php',dict(csrf_token=token(html),token=expired,password=PASSWORD,confirm_password=PASSWORD))
    check('invalid, expired, or already used' in html,'Expired password token rejected')
    # 2FA direct URLs, malformed codes, successful verification, and one-use OTP.
    sql('UPDATE users SET two_factor_enabled=1 WHERE id=1')
    try:
        op=client(); _,html,_=request(op,'auth/login.php')
        request(op,'auth/login.php',dict(csrf_token=token(html),email='admin1@example.test',password=PASSWORD,role='admin'))
        _,html,url=request(op,'admin/vendors.php')
        check('/auth/otp_verify.php' in url,'Unverified admin cannot bypass OTP via direct URL')
        sql("UPDATE users SET otp_code='123456',otp_expires=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=1")
        _,html,_=request(op,'auth/otp_verify.php')
        _,html,_=request(op,'auth/otp_verify.php',dict(csrf_token=token(html),otp=''))
        check('Invalid verification code' in html,'Empty OTP rejected')
        _,html,url=request(op,'auth/otp_verify.php',dict(csrf_token=token(html),otp='123456'))
        check('/admin/dashboard.php' in url and sql('SELECT otp_code FROM users WHERE id=1')[0]['otp_code'] is None,'Valid OTP grants access and clears the code')
    finally:
        sql('UPDATE users SET two_factor_enabled=0,otp_code=NULL,otp_expires=NULL WHERE id=1')
        sql('DELETE FROM login_attempts')
    print('Authentication edge cases completed')
