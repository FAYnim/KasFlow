<?php
require_once __DIR__ . '/../bootstrap.php';
$row = db()->query("SELECT username, password FROM pengurus WHERE username='admin'")->fetch();
if (!$row) {
    fwrite(STDERR, "FAIL: admin user missing\n");
    exit(1);
}
if ($row['username'] !== 'admin') {
    fwrite(STDERR, "FAIL: admin username mismatch\n");
    exit(1);
}
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
