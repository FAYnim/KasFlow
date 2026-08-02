<?php
require_once __DIR__ . '/../bootstrap.php';
$row = db()->query("SELECT password FROM pengurus WHERE username='admin'")->fetch();
if (!password_verify('admin123', $row['password'])) {
    fwrite(STDERR, "FAIL: admin password verify\n");
    exit(1);
}
if (password_verify('wrong', $row['password'])) {
    fwrite(STDERR, "FAIL: wrong password accepted\n");
    exit(1);
}
echo "PASS: auth verify logic\n";
exit(0);
