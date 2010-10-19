<?php
header('content-type: text/plain');
$mongo = new Mongo();
$db = $mongo->selectDb('session');

$collection = $db->session;

$remaining = 2000000; // 2 seconds timeout
$timeout = 5000; // 5000 microseconds (5 ms)

$start = microtime(true);
do {
    $success = false;
    
    try {

        $query  = array('_id' => 'session-test', 'lock' => 0);
        $update = array('$set' => array('lock' => 1));
        $options = array('safe' => true, 'upsert' => true);
        $result = $collection->update($query, $update, $options);

        if ($result['ok'] == 1) {
            echo "Got Lock\n"; 
            break; 
        }
    } catch (MongoCursorException $e) {
        if (substr($e->getMessage(), 0, 26) != 'E11000 duplicate key error') {
            throw $e;  // not a dup key? 
        }

        if ($timeout > 50000) {
            $query  = array('_id' => 'session-test');
            $update = array('$set' => array('lock' => 0));
            $options = array('safe' => true);
            $result = $collection->update($query, $update, $options);
        }
    }
    
    usleep($timeout);
    $remaining = $remaining - $timeout;
    $timeout = $timeout * 2; // wait a little longer next time
    echo "Sleep: $timeout | remain: $remaining\n";


} while ($timeout < 1000000 && $remaining > 0);

echo "Time: " . (microtime(true) - $start) . "s\n";