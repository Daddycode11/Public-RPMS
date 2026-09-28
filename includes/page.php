<?php
function pageStart(string $title): void {
    $role=$_SESSION['role'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' | RPMS</title></head><body>';
    if ($role==='admin') require __DIR__.'/../admin/navbar.php';
    elseif ($role==='collector') require __DIR__.'/../collector/collector_navbar.php';
    else require __DIR__.'/../vendor/vendor_navbar.php';
    echo '<main class="rpms-main"><h1>'.h($title).'</h1>';
}
function pageEnd(): void { echo '</main></body></html>'; }
function pagination(int $count, int $page, int $size = 20): void {
    $pages=max(1,(int)ceil($count/$size));
    echo '<nav class="rpms-pagination" aria-label="Pagination">';
    foreach (['First'=>1,'Previous'=>max(1,$page-1),'Next'=>min($pages,$page+1),'Last'=>$pages] as $text=>$number) {
        echo '<a href="?'.h(http_build_query(array_merge($_GET,['page'=>$number]))).'">'.$text.'</a> ';
    }
    echo ' Page '.$page.' of '.$pages.' · '.$count.' records</nav>';
}
