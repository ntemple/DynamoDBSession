<?php
// PHPUnit produces output way too early. We need to start a session
// using the bootstrap mechanism in order to be able to test our
// session handler.
require_once "../MongoSession.php";

$session = new MongoSession();
