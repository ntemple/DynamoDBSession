<?php
header('content-type: text/plain');

if ($_GET['s']) {
    session_id($_GET['s']);
}

$start = microtime(true);
session_start();
$end = microtime(true) - $start;

$fp = fopen('/tmp/php-sessions/results-concurrency.txt', 'a+');
fwrite($fp, "Session Time: $end\n");
fclose($fp);

if (isset($_GET['d1'])) {
    $_SESSION['d1']++;
}

if (isset($_GET['d2'])) {
    $_SESSION['d2']++;
}

if (isset($_GET['d3'])) {
    sleep(1); // 1 second. :b
    $_SESSION['d3']++;
}

echo "Session lock time: ${end}s \n";

print_r($_SESSION);
$total = 0; 
foreach ($_SESSION as $key => $value) {
    $total += $value; 
}

echo "\nTotal Hits: $total\n";