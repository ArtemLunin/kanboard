<?php
ini_set('session.use_cookies',1);
ini_set('session.use_only_cookies',0);
ini_set('session.use_trans_sid',1);
session_name('kanboardSession');
session_start();

require_once 'db_conf.php';
require_once 'classProjects.php';

mb_internal_encoding("UTF-8");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$out_res = [];
$param_error_msg['answer'] = false;
$userID = 0;

function isInt($val) {
    return filter_var($val, FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE, "options" => ["min_range" => 1]]) ?? 0;
}

$project_object = new myProjectUtils\ProjectUtils();

if (!isset($_SESSION['logged_user'])) {
    $param_error_msg['answer'] = 'Unauthorized';
    $out_res = ['error' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
    exit;
} else {
    $userID = $project_object->getUserID($_SESSION['logged_user']);
    if (isset($_SESSION['SUPER_USER']) && $_SESSION['SUPER_USER']) {
        $project_object->setRootAccess(true);
    } else {
        $project_object->setRootAccess(false);
    }
}

if ($userID === 0) {
    $param_error_msg['answer'] = 'Unauthorized';
    $out_res = ['error' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
    exit;
}

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? 0;
$value = trim($paramJSON['value'] ?? '');
$number = trim($paramJSON['number'] ?? '');
$groups = $paramJSON['groups'] ?? 0;
$id = isInt($paramJSON['id'] ?? 0);
$group_id = isInt($paramJSON['group_id'] ?? 0);
$element = $paramJSON['element'] ?? '';
$activity = $paramJSON['activity'] ?? '';
$group_ids = $paramJSON['group_ids'] ?? 0;
$user_name = $paramJSON['user_name'] ?? 0;
$group_fields = $paramJSON['group_fields'] ?? 0;
// $group_idx = isInt($paramJSON['group_idx'] ?? 0);
$text_field = trim($paramJSON['text_field'] ?? '');

if ($method !== 0)
{
    if ($method === 'addProject' && $value && $number && $text_field) {
		$param_error_msg['answer'] = $project_object->addProject($value, $number, $text_field);
	} elseif ($method === 'changeProject' && $id && $value && $number && $text_field) {
        $param_error_msg['answer'] = $project_object->changeProject($id, $value, $number, $text_field);
    } elseif ($method === 'removeProject' && $id) {
        $param_error_msg['answer'] = $project_object->removeProject($id);
    } elseif ($method === 'getProjectsList') {
        $param_error_msg['answer'] = $project_object->getProjectsList();
    } elseif ($method === 'addGroupToProject' && $id && ($group_id || $group_ids)) {
        $param_error_msg['answer'] = $project_object->addGroupToProject($id, $group_id ? $group_id : $group_ids);
    } elseif ($method === 'removeGroupFromProject' && $id) {
        $param_error_msg['answer'] = $project_object->removeGroupFromProject($id);
    } elseif ($method === 'changeProjectActivity' && $id && $group_id && $group_fields) {
        $param_error_msg['answer'] = $project_object->changeProjectActivity($id, $group_id, $group_fields, $element, $activity);
    } elseif ($method === 'getProjectsActivity') {
        $param_error_msg['answer'] = $project_object->getProjectsActivity($id);
    } elseif ($method === 'getProjectsActivityByID' && $id) {
        $param_error_msg['answer'] = $project_object->getProjectsActivityByID($id);
    }
    elseif ($method === 'getProjectsActivityAll') {
        $param_error_msg['answer'] = $project_object->getProjectsActivityAll();
    }
    elseif ($method === 'addGroups' && $groups && count($groups) > 0) {
        $param_error_msg['answer'] = $project_object->addGroups($groups);
    } elseif ($method === 'getGroupsList') {
        $param_error_msg['answer'] = $project_object->getGroupsList();
    } elseif($method === 'getUsersList') {
        $param_error_msg['answer'] = $project_object->getUsersList();
    } elseif($method === 'addUserToGroup' && $user_name && $group_id) {
        $param_error_msg['answer'] = $project_object->addUserToGroup($user_name, $group_id);
    } elseif($method === 'removeUserFromGroup' && $user_name && $group_id) {
        $param_error_msg['answer'] = $project_object->removeUserFromGroup($user_name, $group_id);
    } elseif ($method === 'removeGroup' && $id) {
        $param_error_msg['answer'] = $project_object->removeGroup($id);
    }
    $out_res = ['success' => $param_error_msg];
}
header('Content-type: application/json');
echo json_encode($out_res);

