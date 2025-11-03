<?php
namespace myProjectUtils;

require_once 'classDatabase.php';
require_once 'classHelper.php';

class ProjectUtils extends \helperUtils\helperUtils {
    private $db_object_project = null;
    private $tProjects = [
        "tableName" => "projects",
        "fields" => [
            "name", "number", "description", "start_date", "end_date", "last_activity_date", "status"
        ]
    ];
    private $tGroups = [
        "tableName" => "groups",
        "fields" => [
            "id", "name"
        ]
    ];
    private $tProjectsActivity = [
        "tableName" => "projects_activity",
        "fields" => [
            "id", "project_id", "group_id", "group_idx", "field_json_props", "start_date", "end_date", "last_activity_date", "status"
        ]
    ];
        
    private $userID = 0;
    function __construct () {
        $this->db_object_project = new \mySQLDatabaseUtils\databaseUtilsMOP();
    }
    function addNewProject($p_name, $p_number, $p_description) {
        $fields_obj = [
            "name" => $p_name,
            "number" => $p_number,
            "description" => $p_description,
            "status" => 0,
        ];
        return $this->db_object_project->addObjectToTable($this->tProjects["tableName"], $fields_obj);
    }
    function removeProject($projectID) {
        return $this->db_object_project->removeObjectFromTable($this->tProjects["tableName"], $projectID);
    }
    function removeGroup($groupID) {
        return $this->db_object_project->removeObjectFromTable($this->tGroups["tableName"], $groupID);
    }
    function getUserID($userName) {
        return $this->db_object_project->getUserID($userName);
    }
    function addGroups($groups_list) {
        foreach ($groups_list as $group) {
            $fields_obj = [
                "name" => $group,
            ];
            $this->db_object_project->addObjectToTable($this->tGroups["tableName"], $fields_obj);
        }
        return $this->getGroupsList();
    }
    function getGroupsList() {
        return $this->db_object_project->selectObjectFromTable($this->tGroups["tableName"], [], $this->tGroups["fields"]);
    }
    function getProjectsActivity($project_id) {
        return $this->db_object_project->selectObjectFromTable($this->tProjectsActivity["tableName"], ["project_id" => $project_id], $this->tProjectsActivity["fields"]);
    }
    function getProjectGroupIdx($project_id) {
        $sql = "SELECT COALESCE(MAX(`group_idx`), 0) AS group_idx FROM projects_activity WHERE `project_id`=:param1";
        $group_idx = 0;
        if ($table_res = $this->db_object_project->getSQL($sql, ['param1' => $project_id]))
        {
            $group_idx = $table_res[0]['group_idx'];
        }
        return $group_idx;
    }
    function addGroupsToProject($project_id, $group_id, $group_idx) {
        $fields_obj = [
            "project_id" => $project_id,
            "group_id" => $group_id,
            // "group_idx" => $group_idx,
            "field_json_props" => "",
            "status" => 0,
        ];
        // if ($group_idx == 0) {
            // $group_idx_local = $this->getProjectGroupIdx($project_id) + 1;
        // } else {
            $fields_obj['group_idx'] = $this->getProjectGroupIdx($project_id) + 1;
            $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);

            return $this->getProjectsActivity($project_id);
        // }
        // return $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);
    }
}


