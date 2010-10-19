<?php
header('Content-type: text/plain');

function sess_open($path, $name) {
    echo "session open : $path : $name\n";
}

function sess_close() {
    echo "session close\n";
}

function sess_read($id) {
    echo "session read : id : $id\n";
    return 'bar|s:2:"hi";'; // fake old data
}

function sess_write($id, $data) {
    echo "session write\n";
    $fp = fopen('/tmp/test-session2', "a+");
    if ($fp) {
        $return = fwrite($fp, $data . "\n");
        fclose($fp);
        return true;
    } else {
        return false;
    }
}

function sess_destroy($id) {
    echo "session destroy : id : $id\n";
}

function sess_gc($max) {
    echo "session gc\n";
}

//session_set_save_handler($open, $close, $read, $write, $destroy, $gc);
session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');

session_start();

// the session is *saved* at the end = one write
for ($i=0;$i<5;$i++) {
    $_SESSION['test'] = array('foo' => $i);
}

echo "  Your session id: " . session_id() . "\n";
//session_destroy();