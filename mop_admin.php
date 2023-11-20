<?php
require_once 'db_conf.php';
require_once 'classDatabase.php';

mb_internal_encoding("UTF-8");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$out_res = [];
$param_error_msg['answer'] = [];

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$value = $paramJSON['value'] ?? $_REQUEST['value'] ?? 0;
$parentId = $paramJSON['parentId'] ?? $_REQUEST['parentId'] ?? 0;
$id = $paramJSON['id'] ?? $_REQUEST['id'] ?? 0;

// $db_object = new databaseUtils();
$db_object = new mySQLDatabaseUtils\databaseUtilsMOP();

if ($method !== 0)
{
    if ($method === 'getOGPA') {
        $param_error_msg['answer'] = $db_object->getOGPA();
    } elseif ($method === 'getOGPAActivity' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->getOGPAActivity($value);
	} elseif ($method === 'addPrimeElement' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->addPrimeElement($value);
	} elseif ($method === 'modPrimeElement' && $value && is_string($value) && $id) {
		$param_error_msg['answer'] = $db_object->modPrimeElement($value, $id);
	} elseif ($method === 'addActivity' && $value && $parentId) {
		$param_error_msg['answer'] = $db_object->addActivity($value, $parentId);
	} elseif ($method === 'modActivity' && $value && is_string($value) && $id && $parentId) {
		$param_error_msg['answer'] = $db_object->modActivity($value, $id,  $parentId);
	} elseif ($method === 'delPrimeElement' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->delPrimeElement($value);
	} elseif ($method === 'delActivity' && $value && is_string($value)) {
		$param_error_msg['answer'] = $db_object->delActivity($value);
	} elseif ($method === 'getActivityFields' && $id) {
		$param_error_msg['answer'] = $db_object->getActivityFields($id);
	} elseif ($method === 'setActivityFields' && $id && is_array($value)) {
		$param_error_msg['answer'] = $db_object->setActivityFields($value, $id);
	}
    $out_res = ['success' => $param_error_msg];
}
header('Content-type: application/json');
echo json_encode($out_res);
?>