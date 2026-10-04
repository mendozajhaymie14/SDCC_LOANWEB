<?php
$c = new PDO('mysql:host=127.0.0.1;port=3306;dbname=sdcc;charset=utf8mb4', 'root', '');
echo "=== users (id, name, email, usertype, borrower_status, coop_member_id) ===\n";
foreach ($c->query('SELECT id,name,email,usertype,borrower_status,coop_member_id FROM users ORDER BY id') as $r) { echo implode(' | ', $r) . "\n"; }
echo "=== loan_applications count ===\n";
echo $c->query('SELECT COUNT(*) FROM loan_applications')->fetch()[0] . "\n";
echo "=== loan_applications (id, reference, loan_type, amount, status) ===\n";
foreach ($c->query('SELECT id,reference,loan_type,amount,status FROM loan_applications ORDER BY id') as $r) { echo implode(' | ', $r) . "\n"; }