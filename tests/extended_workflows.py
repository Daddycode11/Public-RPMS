from workflows import *
import io
import zipfile
import xml.etree.ElementTree as ET

if __name__=='__main__':
    admin,collector,vendor=login('admin',1),login('collector',2),login('vendor',3)
    # Natural sorting and pages beyond the original 10-page concern.
    sql("INSERT INTO sections(id,section_name) VALUES(9000,'Scalability Test') ON DUPLICATE KEY UPDATE section_name=VALUES(section_name)")
    for start in range(10000,11000,100):
        users=','.join(f"({i},'Scale','Vendor {i}','Scale Vendor {i}','scale{i}@example.test','unused','vendor','active')" for i in range(start,start+100))
        sql('INSERT IGNORE INTO users(id,first_name,last_name,fullname,email,password,role,status) VALUES '+users)
        vendors=','.join(f"({i},{i},9000,'V-{i-9999:02d}','Scale Vendor {i}',600,20,600,'active',LAST_DAY(CURDATE()))" for i in range(start,start+100))
        sql('INSERT IGNORE INTO vendors(id,user_id,section_id,stall_number,vendor_name,monthly_rent,daily_rent,balance,status,next_due_date) VALUES '+vendors)
    _,html,_=request(admin,'admin/vendors.php?section_id=9000&page=50')
    check('Page 50 of 50' in html and 'Scale Vendor 10999' in html,'1,000 vendors reachable across 50 pages')
    _,html,_=request(admin,'admin/vendors.php?section_id=9000')
    check(html.index('>V-02<')<html.index('>V-10<'),'Stalls sort numerically within section')
    sql("UPDATE vendors SET next_due_date=NULL,balance=600 WHERE id=10000")
    _,html,_=request(collector,'collector/vendors_list.php?search=Scale+Vendor+10000&status=overdue')
    check('No matching vendors' in html,'No due date does not make a new vendor overdue')
    sql("UPDATE vendors SET next_due_date=DATE_SUB(CURDATE(),INTERVAL 1 DAY),balance=600 WHERE id=10000")
    _,html,_=request(collector,'collector/vendors_list.php?search=Scale+Vendor+10000&payment_status=overdue')
    check('Scale Vendor 10000' in html,'Past due date and positive balance produce overdue status')
    # Edit rent without affecting the linked user or inventing a daily rate.
    _,html,_=post(admin,'admin/vendors.php?edit=10000',dict(action='edit',id=10000,vendor_name='Scale Vendor 10000',stall_number='V-01',section_id=9000,monthly_rent='650.50',daily_rent='22.25',balance='650.50',next_due_date='2026-12-31'))
    check('Vendor details saved' in html and float(sql('SELECT daily_rent FROM vendors WHERE id=10000')[0]['daily_rent'])==22.25,'Admin daily/monthly rent editing persists')
    _,html,_=post(admin,'admin/vendors.php?edit=10000',dict(action='edit',id=10000,vendor_name='Scale Vendor 10000',stall_number='V-02',section_id=9000,monthly_rent=600,daily_rent=20,balance=600,next_due_date='2026-12-31'))
    check('already assigned within this section' in html,'Duplicate stall in the same section is rejected')
    # Section writes use CSRF-protected POST; old GET links cannot mutate.
    before=sql('SELECT deleted_at FROM sections WHERE id=9000')[0]['deleted_at']
    request(admin,'admin/sections.php?delete=9000')
    check(sql('SELECT deleted_at FROM sections WHERE id=9000')[0]['deleted_at']==before,'GET cannot remove section')
    post(admin,'admin/sections.php',dict(delete=9000))
    check(sql('SELECT deleted_at FROM sections WHERE id=9000')[0]['deleted_at'] is not None,'Section archive works')
    post(admin,'admin/sections.php',dict(restore=9000))
    check(sql('SELECT deleted_at FROM sections WHERE id=9000')[0]['deleted_at'] is None,'Section restore works')
    # Existing document replacement retains historical file but excludes it from current views.
    doc=sql('SELECT id FROM vendor_documents WHERE vendor_id=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1')[0]
    _,html,_=request(vendor,'vendor/documents.php')
    _,html,_=upload(vendor,'vendor/documents.php',dict(csrf_token=token(html),action='upload',document_type='Updated ID',replace_id=doc['id']),[('document','replacement.png',Path('assets/favicon/favicon-32x32.png').read_bytes())])
    check('submitted for review' in html and sql(f"SELECT deleted_at FROM vendor_documents WHERE id={doc['id']}")[0]['deleted_at'] is not None,'Document replacement archives old version')
    newest=sql('SELECT id FROM vendor_documents WHERE vendor_id=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1')[0]['id']
    binary=vendor.open(BASE+f'vendor/download_document.php?id={newest}')
    check(binary.headers['Content-Type']=='image/png' and binary.read()==Path('assets/favicon/favicon-32x32.png').read_bytes(),'Owner document view returns original bytes inline')
    check(request(vendor,f"vendor/download_document.php?id={doc['id']}")[0]==404,'Archived document is no longer viewable')
    # Workbook archives and every XML part must parse, including empty exports.
    for path in ['admin/reports.php?format=xlsx','admin/reports.php?format=xlsx&from=1900-01-01&to=1900-01-01','admin/export.php?export=vendors&format=xlsx','admin/export.php?export=collections&format=xlsx','admin/export.php?export=overdue&format=xlsx']:
        response=admin.open(BASE+path); workbook=zipfile.ZipFile(io.BytesIO(response.read()))
        for item in workbook.namelist(): ET.fromstring(workbook.read(item))
        check('xl/worksheets/sheet1.xml' in workbook.namelist(),'Native Excel export: '+path)
    # Bulk import identifies section as well as stall, and uses date-based backend adjustments.
    _,html,_=request(admin,'admin/bulk_import.php')
    content=b'stall_number,section,amount_paid,payment_date\nV-02,Scalability Test,600,2024-02-29\nV-03,Scalability Test,600,2024-02-30\n'
    _,html,_=upload(admin,'admin/bulk_import.php',dict(csrf_token=token(html)),[('csv_file','import.csv',content)])
    check('1 of 2 payments imported' in html,'Import accepts leap date and rejects invalid date')
    row=sql("SELECT amount_paid,penalty,discount FROM payments WHERE vendor_id=10001 AND payment_date='2024-02-29' ORDER BY id DESC LIMIT 1")[0]
    check(float(row['penalty'])==120 and float(row['discount'])==0,'Imported leap-day payment has correct penalty')
    # Collector profile uses the real schema column.
    _,html,_=post(collector,'collector/collector_profile.php',dict(action='update_profile',first_name='Collector',last_name='Test',email='collector2@example.test',phone='09000000000'))
    check(sql('SELECT contact_information FROM users WHERE id=2')[0]['contact_information']=='09000000000','Collector contact update uses existing schema')
    _,html,_=request(admin,'admin/payment_calendar.php?year=2024&month=2')
    check(html.count('5% discount')==5 and html.count('20% penalty')==9,'Leap-February calendar shows correct automatic ranges')
    for metric in ['vendors','sections','collectors','collection']:
        status,body,_=request(admin,'admin/dashboard_kpi_ajax.php?metric='+metric)
        check(status==200 and 'value' in json.loads(body),'Dashboard refresh: '+metric)
    check(request(admin,'admin/verify_receipt.php?code=RPMS-RECEIPT-1')[0]==200,'Receipt verification no longer queries missing receipt_code column')
    # Privacy-preserving removal with an existing collection does not erase that collection.
    before=len(sql('SELECT id FROM payments WHERE vendor_id=1'))
    post(admin,'admin/vendors.php',dict(action='remove_vendor',id=1))
    check(len(sql('SELECT id FROM payments WHERE vendor_id=1'))==before,'Vendor with payments can be removed without deleting financial history')
    sql("UPDATE vendors SET deleted_at=NULL,status='active' WHERE id IN(1,2)")
    sql("UPDATE users SET deleted_at=NULL,status='active' WHERE id IN(3,4)")
    print('Extended workflow checks completed')
