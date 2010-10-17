<?php
/**
 * Uses MongoDB as a session handler in PHP
 * 
 */
class MongoSessionHandler
{
    const COLLECTION_NAME = 'Sessions';

    /** @var MongoCollection */
    protected $_mongo;

    /** @var array */
    protected $_config;


    /** @var array */
    protected $_session;

    /**
     * Instantiate
     * 
     * @param array $config
     */
    public function __construct(Array $config)
    {
        $mongo = new Mongo();


        // connect to mongo
        // set up collection
        // go go go
    }

    /**
     * Registers the handler into PHP
     * @param <type> $config
     */
    public static function register(Array $config = array())
    {
        $m = new self($config);

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
     * Open the session, do nothing as we already have a
     * connection to mongo.
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    public function open($path, $name)
    {
        echo "open: $path : $name\n";
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
     * Reads the session from Mongo
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {

    }

    /**
     * Writes the session data back to mongo
     * 
     * @param string $id
     * @param string $data
     */
    public function write($id, $data)
    {

    }

    /**
     * Destroy's the session
     *
     * @param string id
     * @return bool
     */
    public function destroy($id)
    {
        
    }

    /**
     * Triggers the garbage collector
     *
     * @param int $max lifetime of session (remove everything older than this)
     * @return bool
     */
    public function gc($max)
    {

    }


}