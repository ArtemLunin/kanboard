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
    function getMORData($mor_entity) {
        $tableName = '';
        $tableFields = [];
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
            default:
                return false;
                break;
        }
        return $this->db_object_project->selectObjectFromTable($tableName, [], $tableFields, []);
    }
}
