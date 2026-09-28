<?php
function storeVendorDocument(PDO $pdo, int $vendorId, int $userId, array $file, string $type): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Select a complete document upload.');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Documents must be between 1 byte and 10MB.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf'=>['application/pdf'], 'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'], 'png'=>['image/png'],
        'doc'=>['application/msword','application/x-ole-storage','application/CDFV2'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip']];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) throw new RuntimeException('The document content does not match an allowed PDF, image, or Word file.');
    if ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) throw new RuntimeException('Invalid Word document.');
        $valid = $zip->locateName('word/document.xml') !== false;
        $zip->close();
        if (!$valid) throw new RuntimeException('Invalid Word document.');
    }
    $dir = dirname(__DIR__) . '/uploads/documents/';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) throw new RuntimeException('Document storage is unavailable.');
    $name = bin2hex(random_bytes(20)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new RuntimeException('Document could not be saved.');
    try {
        $pdo->prepare('INSERT INTO vendor_documents (vendor_id,document_type,file_name,file_path,uploaded_by) VALUES (?,?,?,?,?)')
            ->execute([$vendorId, substr($type,0,50), substr(basename($file['name']),0,255), 'uploads/documents/'.$name,$userId]);
    } catch (Throwable $e) { unlink($dir . $name); throw $e; }
    return $dir . $name;
}
