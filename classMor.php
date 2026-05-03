<?php
namespace myMORUtils;

require_once 'classDatabase.php';
require_once 'classHelper.php';

class MORUtils extends \helperUtils\helperUtils {
    private $db_object_project = null;
    private $tSite = [
        "tableName" => "sites",
        "fields" => [
            "id", "bu", "region", "province", "site", "site_code", "clli_code", "address", "country"
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
    private $tMOR_fields = [
          "tableName" => "mor_fields",
          "fields" => [
            "id", "group_id", "field_json_props"
          ]
    ];
    private $userID = 0;
    private $userName = '';
    private $root_access = false;
    // private $admin_access = false;
    function __construct () {
        $this->db_object_project = new \mySQLDatabaseUtils\databaseUtilsMOP();
    }
    function getRootAccess() {
        return $this->root_access;
    }
    function setRootAccess($value) {
        $this->root_access = (bool) $value;
    }
    // function getAdminAccess($value) {
    //     return $this->admin_access;
    // }
    // function setAdminAccess($value) {
    //     $this->admin_access = (bool) $value;
    // }
    function getUserID($userName) {
        $this->userID = $this->db_object_project->getUserID($userName);
        $this->userName = $userName;
        return $this->userID;
    }
    function getRights($user, $section) {
        $rights = $this->db_object_project->getRights($user, 'dummypass', true);
        $accessType = false;
        if ($rights != false) {
            foreach ($rights as $item) {
                if (isset($item['sectionName']) && $item['sectionName'] === $section) {
                    if ($this->getRootAccess()) {
                       $accessType = 'admin' ;
                    } else {
                        $accessType = $item['accessType'] ?? false;
                    }
                    break;
                }
            }
        }
        return $accessType;
    }
    function loadDataSite($rows, $refreshTable = true) {
        return $this->db_object_project->runInsertBulk($this->tSite["tableName"], array_slice($this->tSite["fields"], 1), $rows, $refreshTable);
    }
    function loadDataRCPC($rows, $refreshTable = true) {
        return $this->db_object_project->runInsertBulk($this->tRCPC["tableName"], array_slice($this->tRCPC["fields"], 1), $rows, $refreshTable);
    }
    function loadDataCA($rows, $refreshTable = true) {
        return $this->db_object_project->runInsertBulk($this->tCA["tableName"], array_slice($this->tCA["fields"], 1), $rows, $refreshTable);
    }
    function getMORData($mor_entity, $groupID = 0) {
        $tableName = '';
        $tableFields = [];
        $tableFilters = [];
        switch ($mor_entity) {
            case 'site':
                $tableName = $this->tSite["tableName"];
                $tableFields = $this->tSite["fields"];
                break;
            case 'ca':
                $tableName = $this->tCA["tableName"];
                $tableFields = $this->tCA["fields"];
                break;
            case 'rcpc':
                $tableName = $this->tRCPC["tableName"];
                $tableFields = $this->tRCPC["fields"];
                break;
            case 'savedData':
                if ($groupID === 0) {
                    return false;
                }
                $tableName = $this->tMOR_fields["tableName"];
                $tableFields = $this->tMOR_fields["fields"];
                $tableFilters = ["group_id" => $groupID];
                break;
            default:
                return false;
                break;
        }
        return $this->db_object_project->selectObjectFromTable($tableName, $tableFilters, $tableFields, []);
    }
    function getMORUserGroups($groupID = null) {
        return ["groups" => $this->db_object_project->getMORUserGroups($this->userName, $groupID)];
    }
    function saveMORData($groupID, $fields) {
        $groups = $this->getMORUserGroups($this->userName, $groupID);
		if (count($groups["groups"])) {
            if ($this->issetGroup($groupID) === null) {
                $this->db_object_project->runInsertSQL($this->tMOR_fields["tableName"], [
                    'group_id'	=> $groupID,
                    'field_json_props'	=> $fields
                ]);
            } else {
                $this->db_object_project->runUpdateSQL($this->tMOR_fields["tableName"], [
                    'field_json_props'	=> $fields
                ], ["group_id" => $groupID]);
            }
            return true;
	    }
        return false;
	}
    function resetMORData($groupID) {
        $groups = $this->getMORUserGroups($this->userName, $groupID);
        if (count($groups["groups"])) {
            return $this->db_object_project->removeObjectFromTableFilter($this->tMOR_fields["tableName"], ["group_id" => $groupID]);
        }
        return false;
    }
    private function issetGroup($groupID) {
        return $this->db_object_project->selectFieldFromTable($this->tMOR_fields["tableName"], ["group_id" => $groupID], "group_id");
    }
    function writeXLSX($requestArr, $spreadsheet, $morFlag) {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A5', trim($requestArr['mor_type']) ?? '');
        $sheet->setCellValue('C8', trim($requestArr['mor_ca']) ?? '');
        $sheet->setCellValue('C9', trim($requestArr['mor_project_name']) ?? '');
        $sheet->setCellValue('C10', trim($requestArr['mor_project_manager']) ?? '');
        $sheet->setCellValue('C11', trim($requestArr['mor_site']) ?? '');
        $sheet->setCellValue('C12', trim($requestArr['mor_date']) ?? '');
        $sheet->setCellValue('C13', trim($requestArr['mor_requestor']) ?? '');
        $sheet->setCellValue('C14', trim($requestArr['mor_region']) ?? '');
        $sheet->setCellValue('C15', trim($requestArr['mor_requisition']) ?? '');
        $sheet->setCellValue('C16', trim($requestArr['mor_add_info']) ?? '');
        if ($morFlag == 0) {
            $sheet->setCellValue('G8', trim($requestArr['mor_project_contact']) ?? '');
            $sheet->setCellValue('G9', trim($requestArr['mor_phone_number']) ?? '');
            $sheet->setCellValue('G10', trim($requestArr['mor_site_address']) ?? '');
            $sheet->setCellValue('G11', trim($requestArr['mor_site_address2']) ?? '');
            $sheet->setCellValue('G12', trim($requestArr['mor_city']) ?? '');
            $sheet->setCellValue('G13', trim($requestArr['mor_province']) ?? '');
            $sheet->setCellValue('G14', trim($requestArr['mor_postal_code']) ?? '');
            $sheet->setCellValue('G15', trim($requestArr['mor_country']) ?? '');
        }
        $sheet->setCellValue('G16', trim($requestArr['mor_contractor']) ?? '');
        $sheet->setCellValue('G17', trim($requestArr['mor_drop_ship']) ?? '');
        $sheet->setCellValue('G18', trim($requestArr['mor_approving_mgr']) ?? '');
        if ($morFlag) {
            $sheet->setCellValue('G8', trim($requestArr['mor_project_contact_from']) ?? '');
            $sheet->setCellValue('G9', trim($requestArr['mor_phone_number_from']) ?? '');
            $sheet->setCellValue('G10', trim($requestArr['mor_site_address_from']) ?? '');
            $sheet->setCellValue('G11', trim($requestArr['mor_site_from']) ?? '');
            $sheet->setCellValue('G12', trim($requestArr['mor_project_contact_to']) ?? '');
            $sheet->setCellValue('G13', trim($requestArr['mor_phone_number_to']) ?? '');
            $sheet->setCellValue('G14', trim($requestArr['mor_site_address_to']) ?? '');
            $sheet->setCellValue('G15', trim($requestArr['mor_site_to']) ?? '');
        }
        if (isset($requestArr['mor_rcpc'])) {
            $rows_count = count($requestArr['mor_rcpc']);
        }
        $rowExcel = 23;
        for ($row = 0; $row < $rows_count; $row++) { 
            $sheet->fromArray([
                $requestArr['mor_rcpc'][$row],
                $requestArr['mor_vendor_name'][$row],
                $requestArr['mor_vendor_part'][$row],
                $requestArr['mor_part_descr'][$row],
                $requestArr['mor_quantity'][$row],
                $requestArr['mor_uom'][$row],
                $requestArr['mor_oracle'][$row],
                $requestArr['mor_task'][$row],
                $requestArr['mor_site_code'][$row],
                $requestArr['mor_date_required'][$row],
                $requestArr['mor_org'][$row],
                $requestArr['mor_supplier_notes'][$row],
                ], 
                NULL, 
                'A'. $rowExcel);
            $rowExcel++;
        }
        return $spreadsheet;
    }
}
