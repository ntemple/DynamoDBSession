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

        print_r($result);
        if ($result['ok'] == 1) {

            $success = true;

            break; 
        }
    } catch (MongoCursorException $e) {
        if (substr($e->getMessage(), 0, 26) != 'E11000 duplicate key error') {
            throw $e;  // not a dup key? 
        }
    }
    
    echo "Sleep: $timeout | remain: $remaining\n";

    usleep($timeout);
    $remaining = $remaining - $timeout;
    $timeout = $timeout * 2; // wait a little longer next time
} while ($timeout < 1000000 && $remaining > 0);

echo "Time: " . (microtime(true) - $start) . "s\n";