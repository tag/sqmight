<?php

/**
 * @author Tom Gregory <tom@alt-tag.com>, © 2012
 *
 * Custom session handler; creates/destroys user db instances and session data.
 * Implemented as a Singlton.
 */
class SQM_SessionHandler
{
    private static $instance; // Singleton
	static private $dbPath = './app/data/session.sqlite3';
	private $db;

    private function __construct() {
		session_set_save_handler(
			array($this, "open"),
			array($this, "close"),
			array($this, "read"),
			array($this, "write"),
			array($this, "destroy"),
			array($this, "clean"));

		register_shutdown_function('session_write_close'); // Triggers session shutdown before objects are unloaded
		session_start();
    }

    public static function singleton()
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Unserializing is not allowed.', E_USER_ERROR);
    }

	/***
	 * Opens session database for reading. Params not used, but provided per spec.
	 * See e.g., http://us2.php.net/manual/en/function.session-set-save-handler.php
	 *
	 * @param string $save_path
	 * @param string $session_name
	 * @return bool success
	 */
	function open($save_path, $session_name) {
		$this->db = new PDO('sqlite:'.self::$dbPath);

		assert($this->db);

	    return TRUE;
	}

	/***
	 * Closes db connection by nulling PDO object
	 *
	 * @return bool success
	 */
	function close() {
		$this->db = null;
		return TRUE;
	}

	/**
	 * Reads session data. Parsed automatically by PHP
	 *
	 * @return string encoded session data; empty string if no data
	 */
	public function read($id) {
		assert($this->db);

		$sql = $this->db->prepare('SELECT data FROM sessions WHERE id = ?');
		$sql->execute(array($id));

		$data = $sql->fetch(PDO::FETCH_ASSOC);

		return $data === FALSE ? '' : $data['data'];
	}

	/**
	 * Writes session data, which is already encoded by PHP
	 *
	 * @param string session id
	 * @return bool success
	 */

	public function write($id, $data) {
		assert($this->db);

		$sql = $this->db->prepare('REPLACE INTO sessions (id, access_time, data) VALUES (?, ?, ?)');
		return $sql->execute(array($id,  time(), $data));
	}

	/**
	 * Destroys session data
	 *
	 *
	 * @return bool success; no return value specificed by PHP docs
	 */

	public function destroy($id) {
		assert($this->db);

		$sql = $this->db->prepare('DELETE FROM sessions WHERE id = ?');
		#TODO: Verify key, remove associated user db
		return $sql->execute(array($id));
	}

	/**
	 * @param int  max session lifetime (seconds)
	 */
	public function clean($max) {
		assert($this->db);

		$old = time() - $max;

		$getData = $this->db->prepare('DELETE FROM sessions	WHERE access_time < ?');

		#TODO: Verify key, remove associated user db

		return $getData->execute(array($old));
	}
}

/***

CREATE TABLE sessions
(
    id text NOT NULL,
    access_time int DEFAULT 0,
    data text DEFAULT '',
    PRIMARY KEY (id)
);

***/

$GLOBALS['session_handler'] = SQM_SessionHandler::singleton();


