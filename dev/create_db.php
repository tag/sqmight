<?php
require_once('../app/common.php');

error_log(__FILE__.': loaded common');

### Simple input checking
if (!isset($_POST['create'])) {
	errorAndDie('Bad referrer query.');
}

### Open User's Session and DbConnection
require_once('app/session.php');
require_once('app/user_data.php');

$userDbHandler = new SQM_UserDbHandler();
$userDbHandler->createUserDb();
//$userDb = $userDbHandler->getDbConnection();

//header('Content-type: application/json');

echo '{"response":"created","session":';
echo json_encode($_SESSION);
echo '}';
