<?php
require_once('./MongoSession.php');

if ($_GET['s']) {
    session_id($_GET['s']);
}

if ($_GET['mongo']) {
    $s = new MongoSession();
} else {
    session_start();
}

echo ++$_SESSION['test'];