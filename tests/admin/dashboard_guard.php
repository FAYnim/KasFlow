<?php
// Test: guard akses dashboard.php.
// - Tanpa session: dashboard.php me-redirect ke login (exit) → output harus kosong (tanpa HTML dashboard).
// - Dengan session: halaman dashboard dirender (memuat judul "Dashboard Bendahara").
// Catatan: header() pada CLI PHP 8.5 tidak mengisi headers_list(), jadi pengujian
// dilakukan lewat perilaku output dengan pola proc_open yang sama seperti guard.php.
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

// 1) Tanpa session → guard redirect + exit, tanpa HTML dashboard yang bocor
[$out, $err] = run_php('$_SESSION=[]; ob_start(); include \'dashboard.php\';');
if ($out . $err !== '') {
    fwrite(STDERR, "FAIL: expected empty output without session, got: " . $out . $err . "\n");
    exit(1);
}
echo "PASS: dashboard guards unauthenticated access\n";

// 2) Dengan session → dashboard dirender
[$out, $err] = run_php(
    'session_start(); $_SESSION[\'admin_logged\']=true; $_SESSION[\'admin_nama\']=\'Test\'; '
    . 'ob_start(); include \'dashboard.php\';'
);
if (strpos($out, 'Dashboard Bendahara') === false) {
    fwrite(STDERR, "FAIL: dashboard not rendered with session. stdout len=" . strlen($out) . " stderr=" . $err . "\n");
    exit(1);
}
echo "PASS: dashboard renders with session\n";
exit(0);
