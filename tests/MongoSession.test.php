<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once '../MongoSession.php';

class MongoSessionTest extends PHPUnit_Framework_TestCase {

	private $mongo;
	private $session;

	public function setUp() {
		$this->mongo = new Mongo();
	}

	public function testSessionWrite() {

		$_SESSION['test'] = "some string";
		$_SESSION['mongo'] = "Cool DB!";

		$expected = session_encode();

		session_write_close();

		$database = $this->mongo->selectDB("session");
		$sessions = $database->selectCollection("session");

		// Only select the session data with id = session_id()
		$data = $sessions->findOne(array('session_id' => session_id()), array('data'));

		$this->assertEquals($expected, $data['data']);
	}
}
