<?php
// Test: no session → 403. Use proc_open to isolate session_start().
$cmd = PHP_BINARY . ' -r "'
    . '$_SESSION=[]; $_REQUEST=[\'action\'=>\'add_siswa\']; $_POST=[\'nama\'=>\'Test\']; '
    . 'ob_start(); include \'api_admin.php\'; $out=ob_get_clean(); '
    . 'echo $out;'
    . '"';
$desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
$proc = proc_open($cmd, $desc, $pipes);
$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
proc_close($proc);
$data = json_decode($out, true);
if (($data['error'] ?? '') !== 'unauthorized') {
    fwrite(STDERR, "FAIL: expected unauthorized, got: $out\n");
    exit(1);
}
echo "PASS: guard returns 403 without session\n";
exit(0);
