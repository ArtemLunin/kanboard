<?php
ini_set('session.use_cookies',1);
ini_set('session.use_only_cookies',0);
ini_set('session.use_trans_sid',1);
session_name('kanboardSession');
session_start();
require 'vendor/autoload.php';

require_once 'db_conf.php';
require_once 'classProjects.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

mb_internal_encoding("UTF-8");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$out_res = [];
$param_error_msg['answer'] = false;
$userID = 0;
$availImgTypes = ['image/png','image/jpeg'];

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
$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
if (stripos($contentType, 'application/json') === 0) {
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'];
    $id = isInt($_POST['id'] ?? 0);
    $activity_id = isInt($_POST['activity_id'] ?? 0);
    $target = $_POST['target'] ?? '';
    $groups = [];
    if (isset($_POST['groups']) && count($_POST['groups']) > 0) {
        foreach ($_POST['groups'] as $group) {
            $groups[] = json_decode($group, true);
        }
    }
}


if ($method !== 0 && $method !== 'getProjectDocs')
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
    } 
    // elseif ($method === 'removeGroupFromProject' && $id) {
    //     $param_error_msg['answer'] = $project_object->removeGroupFromProject($id);
    // } 
    elseif ($method === 'changeProjectActivity' && $id && $group_id && $group_fields) {
        $param_error_msg['answer'] = $project_object->changeProjectActivity($id, $group_id, $group_fields, $element, $activity);
    } elseif ($method === 'getProjectsActivity') {
        $param_error_msg['answer'] = $project_object->getProjectsActivity($id);
    } elseif ($method === 'getProjectsActivityByID' && $id) {
        $param_error_msg['answer'] = $project_object->getProjectsActivityByID($id);
    } elseif ($method === 'getProjectsActivityAll') {
        $param_error_msg['answer'] = $project_object->getProjectsActivityAll();
    } elseif ($method === 'addGroups' && $groups && count($groups) > 0) {
        $param_error_msg['answer'] = $project_object->addGroups($groups);
    } elseif ($method === 'getGroupsList') {
        $param_error_msg['answer'] = $project_object->getGroupsList();
    } elseif ($method === 'getAvailGroupsList') {
        $param_error_msg['answer'] = $project_object->getAvailGroupsList();
    } elseif($method === 'getUsersList') {
        $param_error_msg['answer'] = $project_object->getUsersList();
    } elseif($method === 'addUserToGroup' && $user_name && $group_id) {
        $param_error_msg['answer'] = $project_object->addUserToGroup($user_name, $group_id);
    } elseif($method === 'removeUserFromGroup' && $user_name && $group_id) {
        $param_error_msg['answer'] = $project_object->removeUserFromGroup($user_name, $group_id);
    } elseif ($method === 'removeGroup' && $id) {
        $param_error_msg['answer'] = $project_object->removeGroup($id);
    } elseif ($method === 'addFileToActivity' && $id && $activity_id && ($file_upload = $_FILES['diagram']['tmp_name']) != '' && is_uploaded_file($file_upload) && (in_array($_FILES['diagram']['type'], $availImgTypes))) {
        $param_error_msg['answer'] = $project_object->addFileToProjectActivity($id, $activity_id, $file_upload, $target);
    }
    $out_res = ['success' => $param_error_msg];
} elseif ($method === 'getProjectDocs' && $groups && count($groups) > 0) {
    $filesOut = [];
    $project_name = $project_number = $group_name = '';
    foreach ($groups as $group) {
        $project_activity = $project_object->getProjectsActivityByID($group['activityId']);
        $project_name = $project_activity['detail']['project_name'];
        $project_number = $project_activity['detail']['project_number'];
        $group_name = $project_activity['detail']['group_real_name'];

        if (isset($project_activity['detail']['field_json_props'])) {
            $field_json_props = json_decode($project_activity['detail']['field_json_props'], true);
            if ($field_json_props) {
                if ($group['activityType'] === 'MOR') {
                    $morForReleaseFlag = $field_json_props['morForReleaseFlag'] ?? 0;
                    $filename = tempnam(sys_get_temp_dir(), 'xlsx');
                    $spreadsheetObj = IOFactory::load('template/MOR_template.xlsx');
                    if ($morForReleaseFlag) {
                        $spreadsheetObj = IOFactory::load('template/MOR_template_release.xlsx');
                    }
                    $spreadsheetObj = $project_object->writeXLSX($field_json_props, $spreadsheetObj, $morForReleaseFlag);
                    $writer = new XlsxWriter($spreadsheetObj);
                    $writer->save($filename);
                    $filesOut[] = [
                        'tmp_name' => $filename, 
                        'activityType' => $group['activityType'],
                        'group_name'    => $group_name,
                        'file_type' => 'xlsx'
                    ];
                } elseif ($group['activityType'] === 'DIP') {
                    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template/mop_template.docx');
                    $filename = tempnam(sys_get_temp_dir(), 'docx');
                    $templateProcessor = $project_object->writeDOCX($field_json_props, $project_activity["detail"]["files"], $templateProcessor, $project_object);
                    $templateProcessor->saveAs($filename);
                    helperUtils\DocxProcessor::removeIncludedObjFromDocx($filename);
                    $filesOut[] = [
                        'tmp_name' => $filename, 
                        'activityType' => $group['activityType'], 
                        'group_name'    => $group_name,
                        'file_type' => 'docx'
                    ];
                }
            }
        }
        
    }
    if (count($filesOut) > 0) {
        if (count($filesOut) == 1) {
            $contentType = "Content-Type: application/vnd.ms-word; charset=utf-8";
            $fileExt = ".docx";
            $handle = fopen($filesOut[0]['tmp_name'], "r");
            $contents = fread($handle, filesize($filesOut[0]['tmp_name']));
        } else {
            $contentType = "Content-Type: application/zip;";
            $fileExt = ".zip";
            $output_zip = tempnam(sys_get_temp_dir(), 'zip');
            $zip = new \ZipArchive();
            $zip->open($output_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $param_error_msg['answer'] = [];
            foreach ($filesOut as $file_out) {
                $temp_file_name = 'temp/' . bin2hex(random_bytes(5)) . '.' . $file_out['file_type'];
                $zip->addFile($file_out['tmp_name'], $file_out['group_name'] . '.' . $file_out['file_type']);
                copy($file_out['tmp_name'], $temp_file_name);
                $param_error_msg['answer'][] = $temp_file_name;
            }
            $zip->close();
            $handle = fopen($output_zip, "r");
            $contents = fread($handle, filesize($output_zip));
        }
        header($contentType);
        header("Content-Disposition: attachment; filename=" . "project " . $project_name . $fileExt);
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false);
        
        echo $contents;
        exit;
    }
    $out_res = ['success' => $param_error_msg];
    
}
header('Content-type: application/json');
echo json_encode($out_res);

