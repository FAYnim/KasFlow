<?php
// Test: halaman auth di root.
// - login.php tanpa session → form login dirender (memuat "Login Bendahara").
// - logout.php → session dihancurkan & redirect (output kosong).
// Pola proc_open yang sama dengan tests/admin/dashboard_guard.php.
function run_php(string $code): array {
    $cmd = PHP_BINARY . ' -r "' . $code . '"';
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    return [$out, $err];
}

// 1) login.php tanpa session → form login dirender
[$out, $err] = run_php('session_start(); ob_start(); include \'login.php\';');
if (strpos($out, 'Login Bendahara') === false) {
    fwrite(STDERR, "FAIL: login page not rendered. stderr=" . $err . "\n");
    exit(1);
}
echo "PASS: login page renders\n";

// 2) logout.php → redirect + exit (tanpa output)
[$out, $err] = run_php('$_SESSION=[]; ob_start(); include \'logout.php\';');
if ($out . $err !== '') {
    fwrite(STDERR, "FAIL: expected empty output from logout, got: " . $out . $err . "\n");
    exit(1);
}
echo "PASS: logout redirects (no output)\n";
exit(0);
