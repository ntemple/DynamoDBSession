<?php
/**
 * Uses MongoDB as a session handler in PHP
 * 
 */
class MongoSessionHandler
{
    /** @var MongoCollection */
    protected $_mongo;

    /** @var array */
    protected $_config;

    /**
     * Compare and Swap Token
     *
     * Used to make sure the session document hasn't changed
     * between read() and write(). Ensures consistency without
     * locking.
     * 
     * @var int
     */
    protected $_cas = 0;

    /**
     * Is this session marked for garbage collection?
     * 
     * @var int
     */
    protected $_gc = 0;

    /**
     * What we last saw the document as
     * 
     * @var array
     */
    protected $_doc = array();

    /**
     * Instantiate
     *
     * @param string $db
     * @param string $collection
     * @param array $config
     */
    public function __construct($db, $collection, Array $config)
    {
        $mongo = new Mongo();
        $this->_mongo = $mongo->selectCollection($db, $collection);

        // do something with $config.
    }

    /**
     * Registers the handler into PHP
     * @param string $db
     * @param string $collection
     * @param array $config
     */
    public static function register($db, $collection, $config = array())
    {
        $m = new self($db, $collection, $config);

        // boom.
        session_set_save_handler(
            array($m, 'open'),
            array($m, 'close'),
            array($m, 'read'),
            array($m, 'write'),
            array($m, 'destroy'),
            array($m, 'gc')
        );
    }


    /**
     * Creates an internal mongo session doc.
     *
     * @param string $id
     * @param string $data
     * @param int $expire timestamp of when token expires, false = default
     * @param int $cas  compare and swap token
     * @param int $gc marked for garbage collection
     * @return array
     */
    protected function _createDoc($id, $data='', $ts=false, $cas=0, $gc=0)
    {
        return array(
            '_id'   => $id,
            'gc'    => $gc,
            'ts'    => ($ts) ? $ts : time() + intval(ini_get('session.gc_maxlifetime')), // last touched timestamp
            'cas'   => $cas, // compare / swap token
            'd'     => $data
        );

    }

    /**
     * Returns the internal mongo document, or creates
     * one if it doesn't exist
     *
     * @param string $id
     * @return array | null
     */
    protected function _getDoc($id)
    {
        $doc = $this->_mongo->findOne(array('_id' => $id));

        if (!is_null($doc)) {
            $this->_doc = $doc; 
        } else {
            $this->_doc = $this->_createDoc($id);
            $this->_mongo->insert($this->_doc, array('safe' => true));
        }
        
        return $this->_doc;
    }

    /**
     * Open the session, do nothing as we already have a
     * connection to mongo.
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    public function open($path, $name)
    {
        return true; 
    }

    /**
     * Does nothing.
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }


    /**
     * Reads the session from Mongo, create a document if it
     * doesn't exist. 
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {
        $doc = $this->_getDoc($id);
        return $doc['d'];
    }

    /**
     * Writes the session data back to mongo
     * 
     * @param string $id
     * @param string $data
     */
    public function write($id, $data)
    {
        // don't update if doc is marked for GC
        if ($this->_gc == 1) {
            return false;
        }
        
        for ($retries=0; $retries < 10; $retries++) {
            $newCas = $this->_doc['cas'] + 1;
                        
            $query      = array('_id' => $id, 'cas' => $this->_doc['cas']);
            $doc        = $this->_createDoc($id, $data, time(), $newCas);
            $options    = array('safe' => true);

            $result = $this->_mongo->update($query, $doc, $options);            
            if ($result['updatedExisting'] != 1) {
                usleep(10000); // wait 10ms
                $this->_getDoc($id); // re-read the current session
                continue; 
            } else {
                return true; 
            }
        }

        return false;
    }

    /**
     * Destroy's the session
     *
     * @param string id
     * @return bool
     */
    public function destroy($id)
    {
        $result = $this->_mongo->remove(array('_id' => $id), array('safe' => true));
        return ($result['ok'] == 1);
    }

    /**
     * Triggers the garbage collector, we do this with a mongo
     * safe=0 delete, as that will return immediately without
     * blocking php
     *
     * @param int $max lifetime of session (remove everything older than this)
     * @return bool
     */
    public function gc($max)
    {

    }


}