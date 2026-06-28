<?php
ini_set('session.use_cookies',1);
ini_set('session.use_only_cookies',0);
ini_set('session.use_trans_sid',1);
session_name('kanboardSession');
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );

require_once 'db_conf.php';
require_once 'classMor.php';

mb_internal_encoding("UTF-8");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

$out_res = [];
$param_error_msg['answer'] = false;
$userID = 0;
$accessType = false;

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$morType = $_REQUEST['morType'] ?? 0;
$morForReleaseFlag = $_REQUEST['morForReleaseFlag'] ?? 0;
$env = $paramJSON['env'] ?? $_REQUEST['env'] ?? 0;
$value = trim($paramJSON['value'] ?? '');
$id = $paramJSON['id'] ?? 0;
$id = (int)$id;
$number = trim($paramJSON['number'] ?? '');
$submitMOR = isset($_REQUEST['submitMOR']) ? 1 : 0;
// $morGroupID = isset($_REQUEST['morGroupID']) ? (int)$_REQUEST['morGroupID'] : 0;

$mor_object = new myMORUtils\MORUtils();
if (!isset($_SESSION['logged_user'])) {
    $param_error_msg['answer'] = 'Unauthorized';
    $out_res = ['error' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
    exit;
} else {
    $userID = $mor_object->getUserID($_SESSION['logged_user']);
    if (isset($_SESSION['SUPER_USER']) && $_SESSION['SUPER_USER']) {
        $mor_object->setRootAccess(true);
    } else {
        $mor_object->setRootAccess(false);
    }
    $accessType = $mor_object->getRights($_SESSION['logged_user'], $env);
}
if ($userID === 0) {
    $param_error_msg['answer'] = 'Unauthorized';
    $out_res = ['error' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
    exit;
}

if ($method !== 0)
{
    if ($method === 'getMORData') {
		$param_error_msg['answer'] = $mor_object->getMORData($value, $id);
    } elseif ($method == 'loadMORCA' && $morType && ($mor_object->getRootAccess() || $accessType === 'admin')) {
		$tmp = $_FILES['file']['tmp_name'];
        if (($tmp != '') && is_uploaded_file($tmp)) 
        {
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmp);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestDataColumn();
            $highestRow    = $worksheet->getHighestDataRow();

            $rows = $worksheet->rangeToArray(
                "A1:{$highestColumn}{$highestRow}",
                null,
                true,
                true,
                true
            );
            $param_error_msg['table'] = $morType;
            if ($morType == 'ca') {  
                $param_error_msg['answer'] = $mor_object->loadDataCA($rows);
            } elseif ($morType == 'rcpc') {
                $param_error_msg['answer'] = $mor_object->loadDataRCPC($rows);
            } elseif ($morType == 'site') {
                $param_error_msg['answer'] = $mor_object->loadDataSite($rows);
            }
        }
    } elseif ($method === 'clearMORTable' && $value && ($mor_object->getRootAccess() || $accessType === 'admin')) {
        if ($value == 'clearCA') {  
            $param_error_msg['answer'] = $mor_object->clearDataCA();
        } elseif ($value == 'clearRCPC') {
            $param_error_msg['answer'] = $mor_object->clearDataRCPC();
        } elseif ($value == 'clearSite') {
            $param_error_msg['answer'] = $mor_object->clearDataSite();
        }
    } elseif ($method === 'getMORUserGroups') {
        $param_error_msg['answer'] = $mor_object->getMORUserGroups();
    } elseif ($method == 'saveMORData' && $id != 0) {
        $param_error_msg['answer'] = $mor_object->saveMORData($id, $value);
    } elseif ($method == 'resetMORData' && $id != 0) {
        $param_error_msg['answer'] = $mor_object->resetMORData($id);
    }
    $out_res = ['success' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
} elseif ($submitMOR) {
    $filename = tempnam(sys_get_temp_dir(), 'xlsx');
    $spreadsheetObj = IOFactory::load('template/MOR_template.xlsx');
    if ($morForReleaseFlag) {
        $spreadsheetObj = IOFactory::load('template/MOR_template_release.xlsx');
    }
    $spreadsheetObj = $mor_object->writeXLSX($_REQUEST, $spreadsheetObj, $morForReleaseFlag);
    $writer = new XlsxWriter($spreadsheetObj);
    $writer->save($filename);

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=MOR_final.xlsx");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    echo $contents;
} else {
    $out_res = ['success' => false];
    header('Content-type: application/json');
    echo json_encode($out_res);
}

