<?php
require_once('../app/common.php');

### Simple input checking
if (!isset($_POST['go']) || !isset($_POST['sql'])) {
	errorAndDie('Empty query');
}

$sql = trim($_POST['sql']);
if (empty($sql)) {
	echo $app->render('error', array('error'=>'Empty query.'));
	exit;
}

if (strlen($sql) > 1024) {
	echo $app->render('error', array('error'=>'Query too long. 1024 character limit.'));
	exit;
}

#TODO: Validate query — Input checking

### Open User's Session and DbConnection
require_once('app/session.php');
require_once('app/user_data.php');

$app = new ApplicationController();

$userDbHandler = new SQM_UserDbHandler();
$userDb = $userDbHandler->getDbConnection();

if (!$userDb) {
	echo $app->render('error', array('error'=>'No database instance found for session.'));
	exit;
}

$result = $userDb->query($sql); # $result is PDOStatement object or FALSE on error

### Handle db/query, errors
if (!$result) {
	#TODO: better error handling
	$err = $userDb->errorInfo();
	echo $app->render('error', array('error'=>'<strong>Database error:</strong> '.H($err[2])));
	exit;
}

### Output PDOStatement
ob_start();
	$data = $result->fetchAll(PDO::FETCH_ASSOC);
	$columns = array();

	if (empty($data)) {
		echo '(empty)';
	} else {
		$columns = array_keys($data[0]);
		echo $app->render('sql_table', array('data'=>$data, 'columns'=>$columns));
	}
ob_end_flush();