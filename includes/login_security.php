<?php
function loginAttemptKeys(string $email, string $ip): array {
    return [hash('sha256','account:'.strtolower(trim($email))),hash('sha256','ip:'.$ip)];
}
function loginCooldown(PDO $pdo, array $keys): int {
    $wait=0;
    foreach ($keys as $key) {
        $q=$pdo->prepare('SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),locked_until)) FROM login_attempts WHERE attempt_key=?'); $q->execute([$key]);
        $wait=max($wait,(int)$q->fetchColumn());
    }
    return $wait;
}
function recordLoginFailure(PDO $pdo, array $keys): void {
    foreach ($keys as $key) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT IGNORE INTO login_attempts (attempt_key,updated_at) VALUES (?,NOW())')->execute([$key]);
            $q=$pdo->prepare('SELECT failures,locked_until FROM login_attempts WHERE attempt_key=? FOR UPDATE'); $q->execute([$key]); $row=$q->fetch(PDO::FETCH_ASSOC);
            $failures=$row['locked_until'] && strtotime($row['locked_until'])<=time()?1:(int)$row['failures']+1;
            $pdo->prepare('UPDATE login_attempts SET failures=?,locked_until=IF(? >= 3,DATE_ADD(NOW(),INTERVAL 15 SECOND),NULL),updated_at=NOW() WHERE attempt_key=?')->execute([$failures,$failures,$key]);
            $pdo->commit();
        } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
