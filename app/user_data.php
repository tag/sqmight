<?php
/**
 * A collection of methods for creating/handling the user's database instance.
 */

class SQM_UserDbHandler
{
	protected $db;

	/**
	* Relies on $_SESSION['dbName'], session auth token, and user's key cookie via $this->authUserDb()
	*
	* @return objec|bool PDOConnection on success, else FALSE
	**/

	public function getDbConnection() {
		if (!$this->authUserDb()) {return FALSE;} #TODO: Add error logging

		if (!$this->db) {
			// Confirm dbName is alphanumeric. (It shouldn't ever change.)
			assert(ctype_alnum($_SESSION['dbName']));
			error_log(USER_DATA_PATH.'/'.$_SESSION['dbName'].'.sqlite3');
			try {
				$this->db = new PDO(
					'sqlite:'.USER_DATA_PATH.'/'.$_SESSION['dbName'].'.sqlite3');
			} catch (Exception $e) {
				error_log('ERROR OPENING USER DB:' . USER_DATA_PATH .
					'/'.$_SESSION['dbName'].'.sqlite3');
				throw $e;
			}
		}
		return $this->db;
	}

	public function createUserDb() {
		// Remove old DB, if exists
		$this->destroyUserDb();

		$_SESSION['dbName'] = $this->generateToken();
		$key = hash_hmac('sha256', $this->generateToken(32) .
			(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?
				$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'])
			, time());

		setcookie('key', $key, time()+ini_get('session.gc_maxlifetime'), '/');

		# hash MUST match sha512(key, $dbName) or DB no worky
		$_SESSION['auth'] = $this->userHash($key);

		error_log('sqlite3 '.USER_DATA_PATH.'/'.$_SESSION['dbName']
			.'.sqlite3 < ./app/data/base.dump.sql');
		$tmp = exec('sqlite3 '.USER_DATA_PATH.'/'.$_SESSION['dbName']
			.'.sqlite3 < ./app/data/base.dump.sql');
		error_log('Exec output:' . $tmp);
	}

	public function destroyUserDb() {
		if (!$this->authUserDb()) {return FALSE;}
error_log('SESSION DESTROY: destroyUserDb');
		unlink(USER_DATA_PATH.'/'.$_SESSION['dbName'].'.sqlite3');

		setcookie('key', '', time() - 3600, '/'); // Negative time deletes cookie

		$_SESSION = array(); // Clean out session variables
		if (session_id() != '') {
			session_destroy();
			session_start();
		}
	}

	public function exportUserDb() {
		assert(FALSE); //TODO: exportUserDb not yet completed
		if (!$this->authUserDb()) {return FALSE;}

		$tmp = exec('sqlite3 '.USER_DATA_PATH.'/'.$_SESSION['dbName'].'.sqlite3 .dump');

		echo $tmp;
	}


	/**
	 * Relies on $_SESSION['dbName'], session auth token, and user's 'key' cookie
	 * @return bool success
	 */
	public function authUserDb() {
		if (!isset($_SESSION['dbName'])
			|| ! isset($_SESSION['auth'])
			|| ! isset($_COOKIE['key'])) {return FALSE;}

		// Update timestamp on hash cookie
		setcookie('key', $_COOKIE['key'], time()+ini_get('session.gc_maxlifetime'), '/');

		// hash MUST match sha512(key . IP_addr, $dbName) or DB doesn't authenticate
		$hash = $this->userHash($_COOKIE['key']);

		return $hash === $_SESSION['auth'];
	}

	/**
	 * @param string has of user supplied key + IP address using dbName
	 * @return bool success
	 */
	protected function userHash($key) {
		return hash_hmac('sha512', $key .
			(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?
				$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'])
			, $_SESSION['dbName']);
	}

	/**
	 * @param int  token length
	 * @return string random token
	 */
	protected function generateToken($len = 20) {
		$chars = array(0,1,2,3,4,5,6,7,8,9,0,
			'a','b','c','d','e','f','g','h','i','j','k','m',
			'n','o','p','q','r','s','t','u','v','w','x','y','z');
		shuffle($chars);
		return implode('', array_slice($chars, 0, $len));
	}
}
