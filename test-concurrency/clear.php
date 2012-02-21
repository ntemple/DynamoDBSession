<?php
/**
 * Clears all PHP sessions out of Dynamo by dropping the table
 */
header('Content-type: text/plain');
require_once('../DynamoSessionHandler.php');
DynamoSessionHandler::register('session', 'session');

DynamoSessionHandler::getInstance()->getTable()->dropTable();
echo "Sessions Cleared\n";
