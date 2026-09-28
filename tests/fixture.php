<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../config/database.php';
$source=$pdo->query('SELECT DATABASE()')->fetchColumn();
$name='rpms_revision_test';
if($source===$name) throw new RuntimeException('Run fixture creation with the normal database selected.');
$exists=$pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$exists->execute([$name]);
if($exists->fetchColumn()) throw new RuntimeException('Test database already exists; do not overwrite it.');
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $pdo->exec("CREATE TABLE `$name`.`$table` LIKE `$source`.`$table`");
$pdo->exec("USE `$name`");
$password=password_hash('Revision-Test-Password-2026!',PASSWORD_DEFAULT);
foreach([[1,'admin','active'],[2,'collector','active'],[3,'vendor','active'],[4,'vendor','active'],[5,'collector','pending'],[6,'collector','inactive'],[7,'collector','active']] as [$id,$role,$status]) {
    $pdo->prepare('INSERT INTO users(id,first_name,last_name,fullname,email,password,role,status,two_factor_enabled) VALUES (?,?,?,?,?,?,?,?,0)')->execute([$id,ucfirst($role),'Test',ucfirst($role).' Test',$role.$id.'@example.test',$password,$role,$status]);
}
$pdo->exec("INSERT INTO sections(id,section_name) VALUES(1,'Dry Goods'),(2,'Fresh Fish')");
$pdo->exec("INSERT INTO vendors(id,user_id,section_id,stall_number,vendor_name,monthly_rent,daily_rent,balance,status,next_due_date) VALUES (1,3,1,'V-01','Dry Goods Vendor',1000,50,1000,'active',LAST_DAY(CURDATE())),(2,4,2,'V-01','Fish Vendor',1200,60,1200,'active',LAST_DAY(CURDATE()))");
$pdo->exec("INSERT INTO settings(site_name) VALUES('RPMS')");
echo "Isolated test schema and synthetic fixtures ready.\n";
