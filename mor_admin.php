<?php
ini_set('session.use_cookies',1);
ini_set('session.use_only_cookies',0);
ini_set('session.use_trans_sid',1);
session_name('kanboardSession');
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
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

if ($method !== 0)
{
    if ($method === 'getMORData') {
		$param_error_msg['answer'] = $mor_object->getMORData($value);
    } elseif (false) {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load('./others/MOR/rcpc.xlsx');
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
        $mor_object->loadDataRCPC($rows);

        $spreadsheet = $reader->load('./others/MOR/site.xlsx');
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
        $mor_object->loadDataSite($rows);

        $spreadsheet = $reader->load('./others/MOR/ca.xlsx');
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
        $mor_object->loadDataCA($rows);
    }
    $out_res = ['success' => $param_error_msg];
}
header('Content-type: application/json');
echo json_encode($out_res);

