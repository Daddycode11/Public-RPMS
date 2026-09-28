<?php
require __DIR__.'/../includes/payment_rules.php';
$count=0;
foreach([2024,2025,2026,2100] as $year) foreach(range(1,12) as $month) {
    $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
    foreach(range(1,(int)$first->format('t')) as $day) {
        $date=$first->setDate($year,$month,$day); $r=calculatePaymentAdjustment(1000,$date);
        $expected=$day<=5?950:($day<=20?1000:1200);
        if(abs($r['total']-$expected)>0.001) throw new RuntimeException('Wrong payment total on '.$date->format('Y-m-d'));
        if($r['discount']>0 && $r['penalty']>0) throw new RuntimeException('Overlapping rules.');
        $count++;
    }
}
foreach([0,0.01,10.55,999999.99] as $base) {
    $r=calculatePaymentAdjustment($base,new DateTimeImmutable('2024-02-29'));
    if($r['total']!==round(round($base,2)+round($base*.2,2),2)) throw new RuntimeException('Rounding failed.');
}
echo "Payment rules: $count calendar dates and rounding cases passed.\n";
