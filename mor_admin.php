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
$env = $paramJSON['env'] ?? $_REQUEST['env'] ?? 0;
$value = trim($paramJSON['value'] ?? '');
$number = trim($paramJSON['number'] ?? '');

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
		$param_error_msg['answer'] = $mor_object->getMORData($value);
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
            if ($morType == 'ca') {  
                $param_error_msg['answer'] = $mor_object->loadDataCA($rows);
            } elseif ($morType == 'rcpc') {
                $param_error_msg['answer'] = $mor_object->loadDataRCPC($rows);
            } elseif ($morType == 'site') {
                $param_error_msg['answer'] = $mor_object->loadDataSite($rows);
            }
        }
    }
    $out_res = ['success' => $param_error_msg];
    header('Content-type: application/json');
    echo json_encode($out_res);
} elseif (isset($_REQUEST['submitMOR'])) {
    $filename = tempnam(sys_get_temp_dir(), 'xlsx');
    $spreadsheet = IOFactory::load('template/MOR_template.xlsx');
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A5', trim($_REQUEST['mor_type']) ?? '');
    $sheet->setCellValue('C8', trim($_REQUEST['mor_ca']) ?? '');
    $sheet->setCellValue('C9', trim($_REQUEST['mor_project_name']) ?? '');
    $sheet->setCellValue('C10', trim($_REQUEST['mor_project_manager']) ?? '');
    $sheet->setCellValue('C11', trim($_REQUEST['mor_site']) ?? '');
    $sheet->setCellValue('C12', trim($_REQUEST['mor_date']) ?? '');
    $sheet->setCellValue('C13', trim($_REQUEST['mor_requestor']) ?? '');
    $sheet->setCellValue('C14', trim($_REQUEST['mor_region']) ?? '');
    $sheet->setCellValue('C15', trim($_REQUEST['mor_requisition']) ?? '');
    $sheet->setCellValue('C16', trim($_REQUEST['mor_add-info']) ?? '');
    $sheet->setCellValue('G8', trim($_REQUEST['mor_project_contact']) ?? '');
    $sheet->setCellValue('G9', trim($_REQUEST['mor_phone_number']) ?? '');
    $sheet->setCellValue('G10', trim($_REQUEST['mor_site_address']) ?? '');
    $sheet->setCellValue('G11', trim($_REQUEST['mor_site_address2']) ?? '');
    $sheet->setCellValue('G12', trim($_REQUEST['mor_city']) ?? '');
    $sheet->setCellValue('G13', trim($_REQUEST['mor_province']) ?? '');
    $sheet->setCellValue('G14', trim($_REQUEST['mor_postal_code']) ?? '');
    $sheet->setCellValue('G15', trim($_REQUEST['mor_country']) ?? '');
    $sheet->setCellValue('G16', trim($_REQUEST['mor_contractor']) ?? '');
    $sheet->setCellValue('G17', trim($_REQUEST['mor_drop_ship']) ?? '');
    $sheet->setCellValue('G18', trim($_REQUEST['mor_approving_mgr']) ?? '');
    if (isset($_REQUEST['mor_rcpc'])) {
        $rows_count = count($_REQUEST['mor_rcpc']);
    }
    $rowExcel = 23;
    for ($row = 0; $row < $rows_count; $row++) { 
        $sheet->fromArray([
            $_REQUEST['mor_rcpc'][$row],
            $_REQUEST['mor_vendor_name'][$row],
            $_REQUEST['mor_vendor_part'][$row],
            $_REQUEST['mor_part_descr'][$row],
            $_REQUEST['mor_quantity'][$row],
            $_REQUEST['mor_uom'][$row],
            $_REQUEST['mor_oracle'][$row],
            $_REQUEST['mor_task'][$row],
            $_REQUEST['mor_site_code'][$row],
            $_REQUEST['mor_date_required'][$row],
            $_REQUEST['mor_org'][$row],
            $_REQUEST['mor_supplier_notes'][$row],
            ], 
            NULL, 
            'A'.$rowExcel);
        $rowExcel++;
    }
    
    $writer = new XlsxWriter($spreadsheet);
    $writer->save($filename);

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename='MOR_final.xlsx'");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    echo $contents;
}

