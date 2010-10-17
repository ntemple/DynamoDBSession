<?php
require_once('./MongoSessionHandler.php');
MongoSessionHandler::register('session', 'session');

if ($_GET['s']) {
    session_id($_GET['s']);
}

session_start();

if (!isset($_SESSION['c'])) {
    $_SESSION['c'] = 0; 
}

echo "Count: " . ++$_SESSION['c'];
//session_destroy();