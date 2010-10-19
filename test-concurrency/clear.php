<?php
/**
 * Clears all PHP sessions out of Mongo
 */
header('Content-type: text/plain');
require_once('../MongoSessionHandler.php');
MongoSessionHandler::register('session', 'session');

MongoSessionHandler::getInstance()->mongo()->remove();
echo "Sessions Cleared\n";
