<?php
require_once('./MongoSessionHandler.php');
MongoSessionHandler::register('session', 'session');

if ($_GET['s']) {
    session_id($_GET['s']);
}

try {
    session_start();
} catch (Exception $e) {
    header("HTTP/1.0 500 Shit.");
}

if (!isset($_SESSION['c'])) {
    $_SESSION['c'] = 0; 
}

echo "Count: " . ++$_SESSION['c'];
//session_destroy();