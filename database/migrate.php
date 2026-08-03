<?php
require_once __DIR__ . '/../config/database.php';
$dir = __DIR__ . '/migrations';
$files = glob("$dir/*.php");
sort($files);
foreach ($files as $f) {
    require_once $f;
}
echo "\nAll migrations applied.\n";
