<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__.'/../includes/payment_rules.php';
// Get all payments for calendar
$month = max(1,min(12,intval($_GET['month'] ?? date('n'))));
$year = max(1900,min(2100,intval($_GET['year'] ?? date('Y'))));

$payments = $pdo->prepare("
    SELECT p.payment_date, (p.amount_paid-COALESCE(p.discount,0)+COALESCE(p.penalty,0)) AS amount_paid, p.status, v.stall_number,
           CONCAT(u.first_name,' ',u.last_name) AS vendor_name
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.deleted_at IS NULL AND MONTH(p.payment_date) = ? AND YEAR(p.payment_date) = ?
    ORDER BY p.payment_date
");
$payments->execute([$month, $year]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming due dates
$upcomingDue = $pdo->query("
    SELECT v.stall_number, v.next_due_date, v.monthly_rent, v.status,
           CONCAT(u.first_name,' ',u.last_name) AS vendor_name
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    WHERE v.deleted_at IS NULL AND v.balance>0 AND v.next_due_date IS NOT NULL
    ORDER BY v.next_due_date ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Group payments by date
$paymentsByDate = [];
foreach ($payments as $p) {
    $day = (int)date('j', strtotime($p['payment_date']));
    $paymentsByDate[$day][] = $p;
}

// Calendar helpers
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startWeekday = date('w', $firstDay);
$monthName = date('F', $firstDay);

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Calendar | RPMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#ea580c;--brand-dark:#b3260c;--brand-light:#ffe4d1;--ink:#2b0d05;--ink-2:#3a5042;--ink-3:#6b8878;--cream:#f0f4f1;--white:#fff;--border:rgba(234,88,12,.12);--radius:14px}
body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink)}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-header{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee}
.card-title{font-size:.95rem;font-weight:700}
.card-body{padding:22px}
.page-header h1{font-family:'Inter',sans-serif;font-size:1.7rem;font-weight:700}
.page-header .sub{font-size:.88rem;color:var(--ink-3);margin-top:4px}

.cal-nav{display:flex;align-items:center;gap:16px}
.cal-nav a{text-decoration:none;color:var(--brand);font-weight:600;font-size:.9rem;padding:6px 12px;border-radius:6px;transition:.2s}
.cal-nav a:hover{background:var(--brand-light)}
.cal-nav .month-label{font-family:'Inter',sans-serif;font-size:1.2rem;font-weight:700}

.calendar{width:100%;border-collapse:collapse;table-layout:fixed}
.calendar th{padding:10px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3);background:var(--cream)}
.calendar td{vertical-align:top;padding:4px;height:100px;border:1px solid #edf2ee;transition:.2s}
.calendar td:hover{background:#fdf6f1}
.calendar td.today{background:#fff7f2;border-color:var(--brand)}
.calendar td.empty{background:#fafbfa}
.day-num{font-size:.82rem;font-weight:600;color:var(--ink);margin-bottom:4px;padding:2px 4px}
.day-event{font-size:.68rem;padding:2px 5px;border-radius:4px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer}
.day-event.paid{background:var(--brand-light);color:var(--brand-dark)}
.day-event.pending{background:#fff7ed;color:#b45309}
.day-event.more{background:var(--cream);color:var(--ink-3);text-align:center;font-weight:600}

.grid-sidebar{display:grid;grid-template-columns:3fr 1fr;gap:20px}

.due-list{display:flex;flex-direction:column;gap:8px}
.due-item{padding:10px 14px;border:1px solid var(--border);border-radius:10px;transition:.2s}
.due-item:hover{border-color:var(--brand);background:#fdf6f1}
.due-item .stall{font-size:.82rem;font-weight:700;color:var(--ink)}
.due-item .name{font-size:.78rem;color:var(--ink-3)}
.due-item .date{font-size:.78rem;font-weight:600;margin-top:4px}
.due-item .date.overdue{color:#dc2626}
.due-item .date.upcoming{color:var(--brand);}

@media(max-width:900px){.grid-sidebar{grid-template-columns:1fr}.calendar td{height:70px}}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Payment Calendar</h1>
            <p class="sub">View payment activity and upcoming due dates on a calendar.</p>
        </div>
    </div>

    <div class="grid-sidebar">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Payment Schedule</span>
                <div class="cal-nav">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Prev</a>
                    <span class="month-label"><?= $monthName ?> <?= $year ?></span>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next &raquo;</a>
                </div>
            </div>
            <div class="card-body" style="padding:12px">
                <table class="calendar">
                    <thead>
                        <tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $today = date('Y-n-j');
                    $day = 1;
                    for ($row = 0; $row < 6; $row++) {
                        if ($day > $daysInMonth) break;
                        echo '<tr>';
                        for ($col = 0; $col < 7; $col++) {
                            if (($row === 0 && $col < $startWeekday) || $day > $daysInMonth) {
                                echo '<td class="empty"></td>';
                            } else {
                                $isToday = ("$year-$month-$day" === $today) ? ' today' : '';
                                echo "<td class=\"$isToday\">";
                                echo "<div class=\"day-num\">$day</div>";
                                $adjustment=calculatePaymentAdjustment(100,new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day)));
                                echo '<small>'.(['discount'=>'5% discount','regular'=>'Regular','penalty'=>'20% penalty'][$adjustment['rule']]).'</small>';
                                if (isset($paymentsByDate[$day])) {
                                    $dayPayments = $paymentsByDate[$day];
                                    $shown = min(count($dayPayments), 3);
                                    for ($i = 0; $i < $shown; $i++) {
                                        $p = $dayPayments[$i];
                                        $cls = strtolower($p['status'] ?? 'paid');
                                        echo '<div class="day-event ' . $cls . '" title="' . htmlspecialchars($p['vendor_name']) . ' - &#8369;' . number_format($p['amount_paid'], 2) . '">';
                                        echo htmlspecialchars($p['stall_number']) . ' &#8369;' . number_format($p['amount_paid'], 0);
                                        echo '</div>';
                                    }
                                    if (count($dayPayments) > 3) {
                                        echo '<div class="day-event more">+' . (count($dayPayments) - 3) . ' more</div>';
                                    }
                                }
                                echo '</td>';
                                $day++;
                            }
                        }
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Upcoming Due Dates</span></div>
            <div class="card-body">
                <div class="due-list">
                    <?php if (empty($upcomingDue)): ?>
                        <p style="text-align:center;color:var(--ink-3);font-size:.85rem">No due dates set.</p>
                    <?php else: ?>
                        <?php foreach ($upcomingDue as $d):
                            $dueDate = strtotime($d['next_due_date']);
                            $isOverdue = $dueDate < time();
                        ?>
                        <div class="due-item">
                            <div class="stall"><?= htmlspecialchars($d['stall_number']) ?></div>
                            <div class="name"><?= htmlspecialchars($d['vendor_name']) ?></div>
                            <div class="date <?= $isOverdue ? 'overdue' : 'upcoming' ?>">
                                <?= $isOverdue ? 'OVERDUE' : 'Due' ?>: <?= date('M d, Y', $dueDate) ?>
                                &middot; &#8369;<?= number_format($d['monthly_rent'], 2) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</main>
</body>
</html>