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
    function getProjectGroupIdx($project_id, $group_id = 0) {
        $sql_params = [];
        $sql = "SELECT COALESCE(MAX(`group_idx`), 0) AS group_idx FROM projects_activity WHERE `project_id`=:param1";
        $sql_params['param1'] = $project_id;
        if ($group_id !== 0) {
            $sql = "SELECT group_idx FROM projects_activity WHERE `project_id`=:param1 AND `group_id`=:param2";
            $sql_params['param2'] = $group_id;
        }
        $group_idx = 0;
        if ($table_res = $this->db_object_project->getSQL($sql, $sql_params))
        {
            $group_idx = (int)$table_res[0]['group_idx'];
        }
        return $group_idx;
    }
    function addGroupToProject($project_id, $group_id) {
        $fields_obj = [
            "project_id" => $project_id,
            "group_id" => $group_id,
            // "group_idx" => $group_idx,
            "field_json_props" => "",
            "status" => 0,
        ];
            $fields_obj['group_idx'] = $this->getProjectGroupIdx($project_id) + 1;
            $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);

            return $this->getProjectsActivity($project_id);
    }
    function changeProjectActivity($project_id, $group_id, $group_fields) {
        if (isset($group_fields['group_idx'])) {
            $current_group_idx = $this->getProjectGroupIdx($project_id, $group_id);
            $group_id_arr = [];
            $nop = false;
            $plus = true;
            $sql = "SELECT `id`, `group_idx` FROM `projects_activity` WHERE `project_id`=:param1 AND ";
            // $sql .= " `group_idx`>:param2 AND `group_idx`<=:param3";
            if ($group_fields['group_idx'] < $current_group_idx) {
                $sql .= " `group_idx`>=:param3 AND `group_idx`<:param2";
            } elseif ($group_fields['group_idx'] > $current_group_idx) {
                $sql .= " `group_idx`>:param2 AND `group_idx`<=:param3";
                $plus = false;
            } else {
                $nop = true;
            }
            $sql .= " ORDER BY `id`";
            if (!$nop && ($table_res = $this->db_object_project->getSQL($sql, ["param1" => $project_id, "param2" => $current_group_idx, "param3" => $group_fields['group_idx']]))) {
                $sql_upd = "UPDATE `projects_activity` SET `group_idx`=:param1 WHERE `id`=:param2";
                foreach ($table_res as $result) {
                    $new_idx = ($plus) ? $result['group_idx'] + 1 : $result['group_idx'] - 1;
                    $this->db_object_project->modSQL($sql_upd , [
                        'param1' => $new_idx,
                        'param2' => $result['id']
                    ], false);
                }
                // array_column($table_res, 'id')
                // $this->db_object_project->errorLog(print_r(array_column($table_res, 'id'), true), 1);
                $sql_upd = "UPDATE `projects_activity` SET `group_idx`=:param1 WHERE `project_id`=:param2 AND `group_id`=:param3";
                $this->db_object_project->modSQL($sql_upd , [
                    'param1' => $group_fields['group_idx'],
                    'param2' => $project_id,
                    'param3' => $group_id,
                ], false);
            }
        }
    }
}


