<?php
/**
* Uses DynamoDB as a session handler in PHP
*
* @author Nick Temple (commercemeister@gmail.com)
* @author Benson Wong (mostlygeek@gmail.com) (Original MongDB implementation)
* @license http://www.opensource.org/licenses/mit-license.html
*/

require_once('aws-sdk-for-php/sdk.class.php');

/*

The MIT License

Copyright (c) 2012 Nick Temple (commercemeister@gmail.com)
Copyright (c) 2010 Benson Wong (mostlygeek@gmail.com)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

class DynamoSessionHandler
{
  /** @var DynamoSessionHandler */
  protected static $_instance;

  /** @var ddbIdTable */
  protected $_table;

  /**
  * Default options for the connection
  *
  * @var array
  */
  //  protected $_defaults = array(
  //  'servers' => array('localhost:27017'),
  //  'options' => array(
  //  'persist' => 'mongo-session'
  //  )
  //  );

  /**
  * Instantiate
  *
  * @param string $db
  * @param string $collection
  * @param array $config for the mongo connection
  */
  protected function __construct(/*$db, $collection, Array $config*/)
  {
    $this->_table = new ddbIdTable('session');
    // $this->_table = new ddbIdTable('session', 10, 10, true);
      
    //    $conf = (empty($config)) ? $this->_defaults : $config; 
    //    $uri = 'mongodb://'.implode(',', $conf['servers']); 

    //    $mongo = new Mongo($uri, $conf['options']);
    //    $this->_mongo = $mongo->selectCollection($db, $collection);

    //    $this->_mongo->ensureIndex(array('_id' => true, 'lock' => true));
    //    $this->_mongo->ensureIndex(array('expire' => true));
  }

  public function getTable() {
    return $this->_table;
  }
  
  
  /**
  * Gets the current instance
  *
  * @return DynamoSessionHandler null if register() has not been called yet
  */
  public static function getInstance()
  {
    return self::$_instance;
  }

  /**
  * Registers the handler into PHP
  * @param string $db
  * @param string $collection
  * @param array $config
  */
  public static function register($db, $collection, $config = array())
  {
    $m = new self(/*$db, $collection, $config*/);
    self::$_instance = $m;

    // boom.
    session_set_save_handler(
    array($m, 'noop'), // open
    array($m, 'noop'), // close 
    array($m, 'read'),
    array($m, 'write'),
    array($m, 'destroy'),
    array($m, 'gc')
    );
  }

  /**
  * Gets a global (across *all* machines) lock on the session
  *
  * @param string $id session id
  */
  protected function _lock($id)
  {
    $remaining = 30000000; // 30 seconds timeout, 30Million microsecs
    $timeout = 5000; // 5000 microseconds (5 ms)

    $result = $this->_table->lock_lock($id);

    if ($result) {
      return true; 
    }

    // Possible solution for locking a non-existant document
    // Either we don't have a document, or we couldn't obtain the lock
    $doc = $this->_table->get($id);
    if (!isset($doc['id'])) {
      $this->write($id, serialize(''));     
    }

    // Now try to get the lock again.
    do {
      $result = $this->_table->lock_lock($id);

      if ($result) {
        return true; 
      }

      usleep($timeout);
      $remaining = $remaining - $timeout;

      // wait a little longer next time, 1 sec max wait
      $timeout = ($timeout < 1000000) ? $timeout * 2 : 1000000;

    } while ($remaining > 0);

    // aww shit. 
    // How can we handle this better?
    $this->_table->lock_release($id); 
    throw new Exception('Could not get session lock');
  }

  /**
  * A no-op function, somethings just aren't worth doing.
  */
  public function noop() {}

  /**
  * Reads the session from DynamoDB
  *
  * @param string $id
  * @return string
  */
  public function read($id)
  {
    $this->_lock($id);
    $doc = $this->_table->get($id, true); // Do a consistent read. Performance?
    if (!isset($doc['d'])) {
      return '';
    } else {
      return $doc['d'];
    }
  }

  /**
  * Writes the session data back to mongo
  * 
  * @param string $id
  * @param string $data
  * @return bool
  */
  public function write($id, $data)
  {
    $doc = array(
    'id'       => $id,
    'lock'      => 0,
    'lock_ts'   => time(),
    'd'         => $data,
    'expire'    => time() + intval(ini_get('session.gc_maxlifetime'))
    );

    $this->_table->save($doc);
    return true; // @todo error checking    

  }

  /**
  * Destroy's the session
  *
  * @param string id
  * @return bool
  */
  public function destroy($id)
  {
    $this->_table->delete($id);
    return true; // @todo error checking    
  }

  /**
  * Triggers the garbage collector, we do this with a mongo
  * safe=false delete, as that will return immediately without
  * blocking php.
  *
  * Maybe it'll delete stuff, maybe it won't. The next GC
  * will get'em.... eventually :)
  *
  * @return bool
  */
  public function gc($max)
  {
    // @todo - garbage collection    
    //    $results = $this->_mongo->remove(
    //    array('expire' => array('$lt' => time()))
    //    );
  }
}

/**
* Table class for DynamoDB with simplifying assumptions.
*/

class ddbIdTable {

  var $TableName;
  var $response;
  var $schema;

  /** @var AmazonDynamoDB */
  var $dynamodb;
  var $request;

  /**
  * Create a class to access a specific DynomDB table
  * 
  * @param mixed $tableName
  * @param mixed $ReadCapacityUnits
  * @param mixed $WriteCapacityUnits
  * @return ddbIdTable
  */
  function __construct($tableName, $ReadCapacityUnits = 10, $WriteCapacityUnits = 5, $create = false) {
        
    $this->dynamodb = new AmazonDynamoDB();
    $this->TableName = $tableName;
    $this->schema = array(
    'TableName' => $tableName, 
    'KeySchema' => array(   'HashKeyElement' => array(   'AttributeName' => 'id', 'AttributeType' => AmazonDynamoDB::TYPE_STRING )),
    'ProvisionedThroughput' => array( 'ReadCapacityUnits' => $ReadCapacityUnits,  'WriteCapacityUnits' => $WriteCapacityUnits  )
    );    

    if ($create) {
      // Create the table if necessary
      $status = $this->describeTable();
      if ($status == 400) {
        $this->createTable($this->schema);
      }
    }
  }

  /**
  * Create a table
  * 
  * @param mixed $schema
  */
  function createTable($schema = null) {
    if ($schema == null) 
      $schema = $this->schema;

    $this->response = $this->dynamodb->create_table($schema);

    // Sleep and poll until it's created
    do {
      sleep(1);
      $this->describeTable();     
    }
    while ((string) $this->response->body->Table->TableStatus !== 'ACTIVE');
    return true;
  }

  function dropTable() {
    $this->response = $dynamodb->delete_table(array(  'TableName' => $this->TableName ));
    return $this->response->isOk();

  }

  /**
  * describe a table, returning the status. 
  * Used to see if it exists.
  * 
  */
  function describeTable() {
    $this->response = $this->dynamodb->describe_table(array('TableName' => $this->TableName));
    return $this->response->status;    
  }

  /**
  * Inserts or updates an item
  * 
  * - if an item with the same id exists, it is replaced
  * - if an item does not exist, it is added
  * 
  * @param string $item
  * @return CFResponse
  */
  function save(&$item) {    
    $item = (array) $item;
    if (! isset($item['id'])) {
      $item['id'] = ddbUtil::uuid();
    } 

    $r = new ddbRequest($this->TableName);
    $r->setItem($item);
    $params = $r->getParams();
    $result = $this->dynamodb->put_item($params);

    return $result;
  }

  /**
  * Get an item by id
  * 
  * @param mixed $id
  * @param mixed $consistent
  * @param mixed $attributes
  * @return mixed
  */

  function get($id, $consistent = false, $attributes = null) {

    $r = new ddbRequest($this->TableName);
    $r->setKey($id);
    $r->setAttributesToGet($attributes);
    $r->setConsistentRead($consistent);

    $params = $r->getParams();
    $this->response = $this->dynamodb->get_item($params);

    if (! $this->response->isOk()) {
      throw new Exception('Invlaid get operation.');
    }

    return ddbUtil::parse_item($this->response->body->Item);
  }

  /**
  * Delete item by id
  * 
  * @param mixed $id
  * @return bool
  */
  function delete($id) {
    $r = new ddbRequest($this->TableName);
    $r->setKey($id);

    $params = $r->getParams();
    $this->dynamodb->delete_item($params);

    return $this->response->isOk();

  }

  function updateAttributes($item, $action = AmazonDynamoDB::ACTION_PUT) {
    $r = new ddbRequest($this->TableName);
    $r->setKey($item['id']);    
    unset($item['id']);
    $r->setUpdates($item,$action);

    $params = $r->getParams();
    $this->response = $this->dynamodb->update_item($params);    
  }

  function updateAttribute($id, $key, $value, $action = AmazonDynamoDB::ACTION_PUT) {
    $item = array(
    'id' => $id,
    $key => $value
    );
    return $this->updateAttributes($item, $action);
  }


  /**
  * Attempts to lock the specified field 
  * if it is a 0. Returns the the old value and previous
  * timestamp on success, FALSE if the lock can't be obtained
  * 
  * @param mixed $id
  * @param mixed $field
  * @param mixed $lock
  * @param mixed $force
  * @return mixed
  */
  function lock_lock($id, $field = 'lock', $lock = true, $force=false) {
    $r = new ddbRequest($this->TableName);

    if ($lock) {
      $expected_value = 0;
      $new_value = 1;
    } else {
      $expected_value = 1;
      $new_value = 0;      
    }
    $r->setKey($id);
    $r->setReturnValues(AmazonDynamoDB::RETURN_UPDATED_OLD);

    if (!$force) {
      $r->setExpected(array($field => $expected_value));
    }

    $r->setUpdates(array($field => $new_value, $field . '_ts' => time()), AmazonDynamoDB::ACTION_PUT);
    $params = $r->getParams();

    $this->response = $this->dynamodb->update_item($params);    

    $status = $this->response->status;
    if ($status == '200') {
      return ddbUtil::parse_item($this->response->body);
    } else {
      return false;
    }
  }

  /**
  * Resets the existing field to 0
  * 
  * @param mixed $id
  * @param mixed $field
  */
  function lock_unlock($id, $field = 'lock') {
    return $this->lock_lock($id, $field, false);
  }

  function lock_release($id, $field= 'lock') {
    return $this->lock_lock($id, $field, false, false);
  }


  // Increment operation
  function increment($id, $field, $value) {    
    $this->updateAttributes(array('id' => $id, $field => $value). AmazonDynamoDB::ACTION_ADD);
  }

}


/**
* Build up a DynamoDB Request from component parts
*/

class ddbRequest {

  var $TableName; 
  var $Key = null;
  var $Item = null;
  var $Expected = null;
  var $ReturnValues = null;
  var $ConsisteRead = false;
  var $AttributesToGet = null;
  var $AttributeUpdates = null;

  function __construct($TableName) {
    $this->TableName = $TableName;
  }

  function getParams() {
    $params = array(
    'TableName' => $this->TableName
    );

    if ($this->Key)  $params['Key'] = $this->Key; 
    if ($this->Item) $params['Item'] = $this->Item;
    if ($this->Expected) $params['Expected'] = $this->Expected;
    if ($this->ReturnValues) $params['ReturnValues'] = $this->ReturnValues;
    if ($this->ConsisteRead) $params['ConsistentRead'] = 'true';
    if ($this->AttributesToGet) $params['AttributesToGet'] = $this->AttributesToGet;
    if ($this->AttributeUpdates) $params['AttributeUpdates'] = $this->AttributeUpdates;

    return $params;
  }

  function setKey($id, $range = null) {
    if ($range) {
      $this->Key = array('HashKeyElement' => ddbUtil::mapType($id),  'RangeKeyElement' => ddbUtil::mapType($range));
    } else {
      $this->Key = array('HashKeyElement' => ddbUtil::mapType($id));
    }
  }


  function setItem($item) {

    if (is_object($item)) $item = (array) $item;

    $record = array();
    foreach ($item as $k => $v) {
      if (strlen("$k$v") > 63*1024) {
        throw new Exception("Attribute too large");   //@todo store in S3, or break up among multiple attributes
      } 
      $record[$k] = ddbUtil::mapType($v);      
    }
    $this->Item = $record;
    return $record;
  }

  function setUpdates($item, $action = AmazonDynamoDB::ACTION_PUT) {

    if (!$this->AttributeUpdates) $this->AttributeUpdates = array();

    foreach ($item as $k=>$v) {
      $this->AttributeUpdates[$k] = array(  'Action' => $action,  'Value' => ddbUtil::mapType($v)   );
    }    
  }

  function setExpected($item) {
    if (!$this->Expected) $this->Expected = array();
    unset($item['id']);

    foreach ($item as $k => $v) {      
      $this->Expected[$k] = array('Value' => ddbUtil::mapType($v));
    }
  }


  function setConsistentRead($value = true) {
    $this->consisteRead = $value;    
  }

  function setAttributesToGet($values = null) {
    $this->AttributesToGet = $values;
  }

  /**
  * Return Options 
  *
  * AmazonDynamoDB::RETURN_ALL_OLD
  * AmazonDynamoDB::RETURN_ALL_NEW
  * AmazonDynamoDB::RETURN_NONE
  * AmazonDynamoDB::RETURN_UPDATED_NEW
  * AmazonDynamoDB::RETURN_UPDATED_OLD
  * @param mixed $value
  */
  function setReturnValues($value = AmazonDynamoDB::RETURN_ALL_OLD) {
    $this->ReturnValues = $value;
  }

}


/**
* Utilities for working with DynamoDB
*/

class ddbUtil {

  /**
  * Maps a PHP data type to those used in DynamoDB
  * Returns either an array (to be used directly in the
  * request) or the actual type.
  *
  *  const TYPE_STRING = 'S';
  *  const TYPE_NUMBER = 'N';
  *  const TYPE_ARRAY_OF_STRINGS = 'SS';
  *  const TYPE_ARRAY_OF_NUMBERS = 'NS';
  * 
  * @param mixed $var
  * @param mixed $return_array
  */
  static function mapType($var, $return_array = true) {

    $vartype = gettype($var);

    switch($vartype) {
      case 'boolean': 
        $var = $var ? 0 : 1; 
        $type = AmazonDynamoDB::TYPE_NUMBER; break; 
      case 'integer':
      case 'double':
      case 'float':         
        $type = AmazonDynamoDB::TYPE_NUMBER; break; 
      case 'string':
        $type = AmazonDynamoDB::TYPE_STRING; break;
      case 'array':         
        // We only support arrays of strings right now        
        $type = AmazonDynamoDB::TYPE_ARRAY_OF_STRINGS;
        $var2 = array();
        // Force all data to string type
        foreach ($var as $item) {
          $var2[] = (string) $item;
        }
        $var = $var2;
        break;

      default:
        throw new Exception('Unmappable type:' . $type);
    }

    if ($vartype != 'array') $var = (string) $var;

    if ($return_array) {
      return array($type => $var);
    } else {
      return $type;
    }

  }

  /**
  * Parse the response for data.
  * 
  * This handles cases of strings and integes, 
  * not necessarily arrays of same.
  * 
  * @param mixed $data
  */
  static function parse_item($data) {
    $item = array();
    $data = (array)$data;
    foreach ($data as $k => $v) {
      $v = array_values((array) $v);
      $item[$k] = array_pop($v);
    }
    return $item;
  }


  /**
  * Create a unique ID
  */
  static function uuid()
  {
    // The field names refer to RFC 4122 section 4.1.2
    return sprintf('%04x%04x%04x%03x4%04x%04x%04x%04x',
    mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
    mt_rand(0, 65535), // 16 bits for "time_mid"
    mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
    bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
    // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
    // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
    // 8 bits for "clk_seq_low"
    mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) // 48 bits for "node"
    );
  }



}



