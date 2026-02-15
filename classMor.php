<?php
namespace myMORUtils;

require_once 'classDatabase.php';
require_once 'classHelper.php';

class MORUtils extends \helperUtils\helperUtils {
    private $db_object_project = null;
    private $tSite = [
        "tableName" => "sites",
        "fields" => [
            "id", "bu", "region", "province", "site", "site_code", "ccli_code", "address", "country"
        ]
    ];
    private $tCA = [
           "tableName" => "ca", 
           "fields" => [
                "id", "ca", "project_num", "project_name", "project_owner"
        ]
    ];
    private $tRCPC = [
           "tableName" => "rcpc", 
           "fields" => [
                "id", "supplier_part", "rcpc", "supplier", "descr"
        ]
    ];
    private $userID = 0;
    private $userName = '';
    private $root_access = false;
    function __construct () {
        $this->db_object_project = new \mySQLDatabaseUtils\databaseUtilsMOP();
    }
    function getRootAccess() {
        return $this->root_access;
    }
    function setRootAccess($value) {
        $this->root_access = (bool) $value;
    }
    function getUserID($userName) {
        $this->userID = $this->db_object_project->getUserID($userName);
        $this->userName = $userName;
        return $this->userID;
    }
    function loadDataSite($rows, $refreshTable = true) {
        $this->db_object_project->runInsertBulk($this->tSite["tableName"], array_slice($this->tSite["fields"], 1), $rows, $refreshTable);
    }
    function loadDataRCPC($rows, $refreshTable = true) {
        $this->db_object_project->runInsertBulk($this->tRCPC["tableName"], array_slice($this->tRCPC["fields"], 1), $rows, $refreshTable);
    }
    function loadDataCA($rows, $refreshTable = true) {
        $this->db_object_project->runInsertBulk($this->tCA["tableName"], array_slice($this->tCA["fields"], 1), $rows, $refreshTable);
    }
    function getSites() {
        $sites = $this->db_object_project->selectObjectFromTable($this->tSite["tableName"], [], $this->tSite["fields"], []);
        return $sites;
    }
}

// $spreadsheet = new Spreadsheet();
// $sheet = $spreadsheet->getActiveSheet();
// $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);
// $spreadsheet->getActiveSheet()->getColumnDimension('L')->setAutoSize(true);
// $rows_count = 0;
// if (isset($_REQUEST['mor_rcpc'])) {
//     $rows_count = count($_REQUEST['mor_rcpc']);
// }
// $sheet->fromArray([
//     'RCPC',
//     'Vendor Name',
//     'Vendor Part #',
//     'Part Description',
//     'Quantity',
//     'UOM',
//     'Oracle #',
//     'Task #',
//     'Site Code',
//     'Date Required (YYYY-MM-DD)',
//     'Org (MRF Only)',
//     'Supplier Notes (Filled by Material Planner)',
// ], 
// NULL, 
// 'A1');
// $rowExcel = 2;
// for ($row=0; $row < $rows_count; $row++) { 
//     $sheet->fromArray([
//         $_REQUEST['mor_rcpc'][$row],
//         $_REQUEST['mor_vendor_name'][$row],
//         $_REQUEST['mor_vendor_part'][$row],
//         $_REQUEST['mor_part_descr'][$row],
//         $_REQUEST['mor_quantity'][$row],
//         $_REQUEST['mor_uom'][$row],
//         $_REQUEST['mor_oracle'][$row],
//         $_REQUEST['mor_task'][$row],
//         $_REQUEST['mor_site_code'][$row],
//         $_REQUEST['mor_date_required'][$row],
//         $_REQUEST['mor_org'][$row],
//         $_REQUEST['mor_supplier_notes'][$row],
//         ], 
//         NULL, 
//         'A'.$rowExcel);
//     $rowExcel++;
// }
// $filename = tempnam(sys_get_temp_dir(), 'xls');
// $writer = new Xls($spreadsheet);
// $writer->save($filename);
// header("Content-Type: application/vnd.ms-excel; charset=utf-8");
// header("Content-Disposition: attachment; filename=export.xls");
// header("Expires: 0");
// header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
// header("Cache-Control: private", false);
// $handle = fopen($filename, "r");
// $contents = fread($handle, filesize($filename));
// echo $contents;