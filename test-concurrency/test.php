<?php
header('Content-Type: text/plain');

require_once('../MongoSessionHandler.php');
MongoSessionHandler::register('session', 'session');

if ($_GET['s']) {
    session_id($_GET['s']);
}

try {
    session_start();
} catch (Exception $e) {
    // a failure
    header("HTTP/1.0 500 oh. shit.");
}

if (isset($_GET['v'])) {
    $start = microtime(true);

    $key = 'k' . $_GET['v'];
    if (isset($_SESSION[$key])) {
        $_SESSION[$key]['c']++;
    } else {
        $_SESSION[$key]['c'] = 1;
    }

    $time = microtime(true) - $start;
    if (isset($_SESSION[$key]['t'])) {
        $_SESSION[$key]['t'] += $time;
    } else {
        $_SESSION[$key]['t'] = $time;
    }
    
}

print_r($_SESSION); 
$totalc = 0;
$totalt = 0;
foreach (array_keys($_SESSION) as $key) {
    $totalc += $_SESSION[$key]['c'];
    $totalt += $_SESSION[$key]['t'];
}
$totalt = round($totalt, 6);
echo "Total Hits: $totalc, Time: ${totalt}s\n";

