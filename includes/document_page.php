<?php
require_once __DIR__.'/page.php';
require_once __DIR__.'/documents.php';
$isAdmin=$_SESSION['role']==='admin'; $message='';
$vendorId=(int)($_GET['vendor_id']??0);
if (!$isAdmin) {
    $q=$pdo->prepare('SELECT id FROM vendors WHERE user_id=? AND deleted_at IS NULL'); $q->execute([$_SESSION['user_id']]); $vendorId=(int)$q->fetchColumn();
    if (!$vendorId) { http_response_code(404); exit('Vendor account not found.'); }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??'';
        if ($action==='upload' && !$isAdmin) {
            $type=trim((string)($_POST['document_type']??''));
            if (!$type || strlen($type)>50) throw new RuntimeException('Enter a document type of up to 50 characters.');
            $pdo->beginTransaction(); $path=null;
            try {
                $replace=(int)($_POST['replace_id']??0);
                if ($replace) {
                    $q=$pdo->prepare('SELECT id FROM vendor_documents WHERE id=? AND vendor_id=? AND deleted_at IS NULL FOR UPDATE'); $q->execute([$replace,$vendorId]);
                    if (!$q->fetchColumn()) throw new RuntimeException('Document to replace is not yours.');
                }
                $path=storeVendorDocument($pdo,$vendorId,(int)$_SESSION['user_id'],$_FILES['document']??[],$type);
                if ($replace) $pdo->prepare('UPDATE vendor_documents SET deleted_at=NOW() WHERE id=? AND vendor_id=?')->execute([$replace,$vendorId]);
                $pdo->commit(); $message='Document submitted for review.';
            } catch(Throwable $e) { $pdo->rollBack(); if ($path) unlink($path); throw $e; }
        } elseif (in_array($action,['remove','review'],true)) {
            $q=$pdo->prepare('SELECT d.id,v.user_id FROM vendor_documents d JOIN vendors v ON v.id=d.vendor_id WHERE d.id=? AND d.deleted_at IS NULL'); $q->execute([(int)($_POST['id']??0)]); $doc=$q->fetch(PDO::FETCH_ASSOC);
            if (!$doc || (!$isAdmin && (int)$doc['user_id']!==(int)$_SESSION['user_id'])) throw new RuntimeException('Document unavailable.');
            if ($action==='review') {
                $status=$_POST['review_status']??'';
                if (!$isAdmin || !in_array($status,['pending','approved','rejected'],true)) throw new RuntimeException('Invalid review.');
                $pdo->prepare('UPDATE vendor_documents SET review_status=? WHERE id=?')->execute([$status,$doc['id']]);
                $message='Document review saved.';
            } else { $pdo->prepare('UPDATE vendor_documents SET deleted_at=NOW() WHERE id=?')->execute([$doc['id']]); $message='Document removed from the list.'; }
        }
    } catch(Throwable $e) { $message=$e instanceof PDOException?'Could not save the document change.':$e->getMessage(); }
}
$search=trim((string)($_GET['search']??'')); $status=(string)($_GET['status']??'');
$q=$pdo->prepare("SELECT v.id,v.stall_number,s.section_name,u.status,COALESCE(NULLIF(v.vendor_name,''),NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),''),u.fullname) AS name,COUNT(d.id) AS documents FROM vendors v JOIN users u ON u.id=v.user_id LEFT JOIN sections s ON s.id=v.section_id LEFT JOIN vendor_documents d ON d.vendor_id=v.id AND d.deleted_at IS NULL WHERE v.deleted_at IS NULL AND (?='' OR u.status=?) AND (v.vendor_name LIKE ? OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? OR v.stall_number LIKE ? OR s.section_name LIKE ?) GROUP BY v.id ORDER BY s.section_name,v.stall_number");
$like='%'.$search.'%'; $q->execute([$status,$status,$like,$like,$like,$like]); $vendors=$q->fetchAll(PDO::FETCH_ASSOC);
$docs=[]; if ($vendorId) { $q=$pdo->prepare('SELECT * FROM vendor_documents WHERE vendor_id=? AND deleted_at IS NULL ORDER BY created_at DESC'); $q->execute([$vendorId]); $docs=$q->fetchAll(PDO::FETCH_ASSOC); }
pageStart($isAdmin?'Vendor Documents':'My Documents');
echo '<p role="status">'.h($message).'</p>';
if ($isAdmin && !$vendorId): ?>
<form class="rpms-filters"><label>Vendor / Stall / Section<input name="search" value="<?= h($search) ?>"></label><label>Account status<select name="status"><option value="">All</option><?php foreach(['pending','active','inactive'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= h($s) ?></option><?php endforeach ?></select></label><button class="rpms-button">Filter</button></form>
<div class="rpms-table-wrap rpms-panel"><table class="rpms-table"><thead><tr><th>Vendor</th><th>Stall</th><th>Section</th><th>Status</th><th>Documents</th></tr></thead><tbody>
<?php $page=max(1,min(max(1,(int)ceil(count($vendors)/20)),(int)($_GET['page']??1))); foreach(array_slice($vendors,($page-1)*20,20) as $v): ?><tr><td><?= h($v['name']) ?></td><td><?= h($v['stall_number']) ?></td><td><?= h($v['section_name']) ?></td><td><?= h($v['status']) ?></td><td><a href="?vendor_id=<?= $v['id'] ?>">View documents (<?= $v['documents'] ?>)</a></td></tr><?php endforeach ?>
<?php if (!$vendors): ?><tr><td colspan="5">No matching vendors.</td></tr><?php endif ?></tbody></table><?php pagination(count($vendors),$page) ?></div>
<?php else: ?>
<?php if ($isAdmin): ?><p><a href="vendor_documents.php">All vendors</a></p><?php else: ?>
<form method="post" enctype="multipart/form-data" class="rpms-panel rpms-filters"><?= csrfField() ?><input type="hidden" name="action" value="upload"><label>Document type<input name="document_type" maxlength="50" placeholder="Permit, ID, contract..." required></label><label>File (maximum 10MB)<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></label><label>Replace existing document<select name="replace_id"><option value="">Upload new</option><?php foreach($docs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['file_name']) ?></option><?php endforeach ?></select></label><button class="rpms-button">Upload</button></form>
<?php endif ?>
<div class="rpms-panel rpms-table-wrap"><table class="rpms-table"><thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th>View</th><th>Print</th><th>Actions</th></tr></thead><tbody>
<?php foreach($docs as $d): $url=($isAdmin?'download_vendor_document.php':'download_document.php').'?id='.$d['id']; ?><tr><td><?= h($d['document_type']) ?></td><td><?= h($d['file_name']) ?></td><td><?= h($d['created_at']) ?></td><td><?= h($d['review_status']) ?></td><td><a href="<?= h($url) ?>" target="_blank" rel="noopener">View</a></td><td><a href="<?= h($url) ?>" target="_blank" rel="noopener" title="Open the document, then use its print command">Open to print</a></td><td>
<?php if ($isAdmin): ?><form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="action" value="review"><select name="review_status"><?php foreach(['pending','approved','rejected'] as $s): ?><option <?= $d['review_status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach ?></select><button>Save review</button></form><?php endif ?>
<form method="post" onsubmit="return confirm('Remove this document from the list?')"><?= csrfField() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="rpms-button danger">Remove</button></form></td></tr><?php endforeach ?>
<?php if (!$docs): ?><tr><td colspan="7">No documents submitted.</td></tr><?php endif ?></tbody></table></div>
<?php endif; pageEnd(); ?>
