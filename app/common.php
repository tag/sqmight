<?php
chdir(dirname(__FILE__).'/..');
set_include_path(getcwd().":.");

// -------------------------
// Set Environment variables
// -------------------------

if(!file_exists('./app/config.json')) {
	die('Missing configuration file. '.getcwd());
}

$tmp = file_get_contents('./app/config.json');
if(!(bool)$tmp) {die('Empty configuration file.');};

$tmp = json_decode($tmp, true);
if (empty($tmp)) {die('Invalid configuration file.');}

$GLOBALS['CONFIG'] = $tmp;

foreach ($GLOBALS['CONFIG']['php'] as $key=>$val) {
  ini_set($key, $val);
}

define ("DEBUG",
  isset($GLOBALS['CONFIG']['debug']) && $GLOBALS['CONFIG']['debug']);

define ("USER_DATA_PATH", isset($GLOBALS['config']['user_data_path']) ?
	$GLOBALS['config']['user_data_path'] : './session');

// --------------------
// Assertions
// --------------------
function _assertCallback($file, $line, $code) {
	$trace = print_r(debug_backtrace(), true);
	error_log($trace);

	$dt = new DateTime();
	$dt_out = $dt->format(DateTime::ISO8601);

	$details = <<<EOT
File: {$file} : {$line}
Code: {$code}
Date: {$dt_out}

Trace: {$trace}

EOT;
	error_log($details);
	error_log(_getErrorState());

	$msg = DEBUG ? "ASSERT FAILED in {$file} [{$line}]: {$code}" : "Assertion failed.";
	errorAndDie($msg);
}

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_CALLBACK, '_assertCallback');

assert(!ini_get("magic_quotes_gpc"));
assert(!ini_get("magic_quotes_runtime"));
assert(!ini_get("register_globals"));

function _getErrorState() {
	$details = "Session: " . print_r($_SESSION, true);

	if (isset($_SERVER['HTTP_HOST'])) {
		$details .= "\nServer: {$_SERVER['HTTP_HOST']}";
	}
	if (isset($_SERVER['REQUEST_URI'])) {
		$details .= "\nRequest: {$_SERVER['REQUEST_URI']}";
	}
	if (isset($_SERVER['QUERY_STRING'])) {
		$details .= "\nParams: {$_SERVER['QUERY_STRING']}";
	}

	return $details;
}
// --------------------
// Exceptions
// --------------------
function _exceptionHandler($e) {
	$msg = 'Uncaught exception ' .get_class($e)." in '" .$e->getFile()."' (".$e->getLine().")\n";
	$msg .= $e->getMessage();
	$code = $e->getCode();
	error_log("{$msg}\n{$code}");

	error_log($e->getTraceAsString());
	error_log(_getErrorState());

	errorAndDie(DEBUG ? $msg : 'Uncaught Exception');
}

set_exception_handler('_exceptionHandler');

// -------------------------
// Global Functions
// -------------------------

/**
 * Shortcut function for htmlentities
 * @param string
 * @return string HTML-ized
 */
function H ($str) {
	return htmlentities($str, ENT_COMPAT, 'UTF-8');
}

/**
 * Prepares a string for use in JavaScript
 * @param string
 * @param string
 * @return string JavaScript-ized
 */
function J ($str, $quoteType = ENT_COMPAT) {
	$str = preg_replace('/\\\\/', '\\\\\\\\' ,$str);
	switch ($quoteType) {
		case ENT_COMPAT:
			$str = preg_replace('/"/', '\\"' ,$str);
			break;
		case ENT_QUOTES:
			$str = preg_replace('/"/', '\\"' ,$str);
			$str = preg_replace('/\'/', '\\\'' ,$str);
			break;
	}
	return utf8_encode($str);
}

function errorAndDie ($msg = 'Unknown error', $output = 'html' ) {
		error_log($msg);
		header("HTTP/1.0 500 Server Error");
		if ($output == 'json') {
			header('Content-type: application/json');
			echo '{"error":"'.J($msg).'"}';
		} else {
			#TODO: if not XHR, show full page.
			echo H($msg);
		}
		exit;
}

// -------------------------
// Application Controller
// -------------------------

class ApplicationController
{
	/**
	 * Cause redirect by relative path, preserving SSL state.
	 * Strips out .. appropriately. Uses $_SERVER['REQUEST_URI'].
	 *
	 * @param string relative path of template from templates root
	 * @param _vars
	 **/
	public function render ($_template = "", $_vars = null, $_type = "html") {
		$T = $_vars ? $_vars : array();

		$_path	= "./app/templates/{$_template}.{$_type}";

		ob_start();
		require $_path;
		return ob_get_clean();
	}

	/**
	 * Cause redirect by relative path, preserving SSL state.
	 * Strips out .. appropriately. Uses $_SERVER['REQUEST_URI'].
	 *
	 * @param string relative path from current URL to next URL
	 **/
	protected function redirect($url) { #, $code = 200) { #TODO: fix HTTP status code
		session_write_close();

		if (!preg_match('/^https?:\/\//', $url)) {
			if (!preg_match('/^\//', $url)) {
				// convert $url from relative to full path
				$url = preg_replace('/\/[^\/]*$/','/', $_SERVER['REQUEST_URI']).$url;

				$count = 0;
				do {
					$url = preg_replace('/[^\/]+\/\.\.\//', '', $url, -1, $count);
				} while ($count);
			}
			$newurl = 'http';
			if (!empty($_SERVER['HTTPS'])) {$newurl .= 's';}
			$newurl .= '://' . $_SERVER['HTTP_HOST'];
			$url = $newurl . $url;
		}
		header("Location: {$url}");
		exit();
	}

	/**
	 * @return bool whether request came from XMLHttpRequest, such as Prototype.
	 *         Can return true if HTTP header exists, or if HTTP Basic Auth detected
	 */
	protected function isXhr() {
		$xhr = g_getArrayValue(apache_request_headers(), 'X-Requested-With', '');
		if ('XMLHttpRequest' == $xhr) {return true;}

		return g_getArrayValue($_SERVER, 'PHP_AUTH_USER')
			&& g_getArrayValue($_SERVER, 'PHP_AUTH_PW');
	}
}