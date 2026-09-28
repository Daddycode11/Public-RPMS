<?php
function archiveVendor(PDO $pdo, int $id): void {
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT user_id FROM vendors WHERE id=? FOR UPDATE'); $q->execute([$id]);
        $user=$q->fetchColumn();
        if (!$user) throw new RuntimeException('Vendor not found.');
        $pdo->prepare("UPDATE vendors SET deleted_at=NOW(), status='inactive' WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE users SET deleted_at=NOW(), status='inactive' WHERE id=? AND role='vendor'")->execute([$user]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function archivePayment(PDO $pdo, int $id, ?int $collectorId = null): void {
    $sql='UPDATE payments SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL';
    $params=[$id];
    if ($collectorId !== null) { $sql.=' AND collector_id=?'; $params[]=$collectorId; }
    $q=$pdo->prepare($sql); $q->execute($params);
    if (!$q->rowCount()) throw new RuntimeException('Payment unavailable or not owned by you.');
}
function removeForm(string $entity, int $id, string $action = ''): string {
    return '<form method="post" action="'.h($action).'" style="display:inline" onsubmit="return confirm(\'Remove this item from the current list? Historical records are preserved.\')">'.csrfField().'<input type="hidden" name="action" value="remove_'.$entity.'"><input type="hidden" name="id" value="'.$id.'"><button class="rpms-button danger">Remove</button></form>';
}
