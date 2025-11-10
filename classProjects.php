<?php
namespace myProjectUtils;

require_once 'classDatabase.php';
require_once 'classHelper.php';

class ProjectUtils extends \helperUtils\helperUtils {
    private $db_object_project = null;
    private $tProjects = [
        "tableName" => "projects",
        "fields" => [
            "name", "number", "description", "user_id", "start_date", "end_date", "last_activity_date", "status"
        ]
    ];
    private $tGroups = [
        "tableName" => "groups",
        "fields" => [
            "id", "name", "users"
        ]
    ];
    private $tProjectsActivity = [
        "tableName" => "projects_activity",
        "fields" => [
            "id", "project_id", "group_id", "group_idx", "field_json_props", "start_date", "end_date", "last_activity_date", "status"
        ]
    ];
    private $tUsers = [
        "tableName" => "users",
        "fields" => [
            "id", "user_name"
        ]
    ];
    private $userID = 0;
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
    function getOwnerProjectID($projectID) {
        return $this->db_object_project->getOwnerProjectID($projectID);
    }
    function userCanEditProject($projectID) {
        // $owner_projectID = $this->getOwnerProjectID($projectID);
        return ($this->getOwnerProjectID($projectID) === $this->userID || $this->getRootAccess());
    }
    function userCanEditActivity($projectID, $groupID) {
        $activity_status = $this->db_object_project->selectFieldFromTable($this->tProjectsActivity["tableName"], ["id" => $activityID], "status");
        // $userInGroup = $this->getUserFromGroups($groupID);
        return ($activity_status === 0 && ($this->userInGroup($groupID) || $this->getRootAccess()));
    }
    function userInGroup($groupID) {
        $users_list = json_decode($this->db_object_project->selectFieldFromTable($this->tGroups["tableName"], ["id" => $groupID], "users"), true);
        if ($users_list !== null && count($users_list) > 0) {
            if (in_array($this->userID, $users_list)) {
                return true;
            }
        }
        return false;
    }
    function addroject($p_name, $p_number, $p_description) {
        $fields_obj = [
            "name" => $p_name,
            "number" => $p_number,
            "description" => $p_description,
            "user_id" => $this->userID,
            "status" => 0,
        ];
        return $this->db_object_project->addObjectToTable($this->tProjects["tableName"], $fields_obj);
    }
    function removeProject($projectID) {
        // $owner_projectID = $this->getOwnerProjectID($projectID);
        // if ($owner_projectID === $this->userID || $this->getRootAccess()) {
        if ($this->userCanEditProject($projectID)) {
            return $this->db_object_project->removeObjectFromTable($this->tProjects["tableName"], $projectID);
        }
        return false;
    }
    function removeGroup($groupID) {
        if ($this->getRootAccess()) {
            return $this->db_object_project->removeObjectFromTable($this->tGroups["tableName"], $groupID);
        }
        return false;
    }
    function removeGroupFromProject($activityID) {
        // $owner_projectID = $this->getOwnerProjectID($projectID);
        // if ($owner_projectID === $this->userID || $this->getRootAccess()) {
        if ($this->userCanEditProject($projectID)) {
            if ($this->getGroupStatus($activityID) !== 0) {
                return false;
            }
            return $this->db_object_project->removeObjectFromTable($this->tProjectsActivity["tableName"], $activityID);
        }
        return false;
    }
    function getUserID($userName) {
        $this->userID = $this->db_object_project->getUserID($userName);
        return $this->userID;
    }
    function addGroups($groups_list) {
        if ($this->getRootAccess()) {
            foreach ($groups_list as $group) {
                $fields_obj = [
                    "name" => $group,
                ];
                $this->db_object_project->addObjectToTable($this->tGroups["tableName"], $fields_obj);
            }
        }
        return $this->getGroupsList();
    }
    function getGroupsList($filters = []) {
        return $this->db_object_project->selectObjectFromTable($this->tGroups["tableName"], $filters, $this->tGroups["fields"]);
    }
    function getUsersList($filters = []) {
        if ($this->getRootAccess()) {
            return $this->db_object_project->selectObjectFromTable($this->tUsers["tableName"], $filters, $this->tUsers["fields"]);
        }
    }
    function addUserToGroup($userName, $groupID) {
        if ($this->getRootAccess() && $this->getUsersList(["user_name" => $userName])) {
            $usersGroup = $this->getGroupsList(["id" => $groupID])[0];
            if (isset($usersGroup) && array_key_exists('users', $usersGroup)) {
                $users_list = json_decode($usersGroup['users'] ?? '', true);
                if ($users_list) {
                    if (in_array($userName, $users_list)) {
                        return $usersGroup;
                    } else {
                        $users_list[] = $userName;
                    }
                } else {
                    $users_list = [$userName];
                }
                $sql_upd = "UPDATE `groups` SET `users`=:param1 WHERE `id`=:param2";
                $this->db_object_project->modSQL($sql_upd , [
                    'param1' => json_encode($users_list),
                    'param2' => $groupID,
                ], false);
                return $this->getGroupsList();
            }
        }
        return false;
    }
    function removeUserFromGroup($userName, $groupID) {
        if ($this->getRootAccess()) {
            $usersGroup = $this->getGroupsList(["id" => $groupID])[0];
            if (isset($usersGroup) && array_key_exists('users', $usersGroup)) {
                $users_list = json_decode($usersGroup['users'] ?? '', true);
                if ($users_list) {
                    if (in_array($userName, $users_list)) {
                        $new_users_list = array_values(array_diff($users_list, [$userName]));
                        $sql_upd = "UPDATE `groups` SET `users`=:param1 WHERE `id`=:param2";
                        $this->db_object_project->modSQL($sql_upd , [
                            'param1' => json_encode($new_users_list),
                            'param2' => $groupID,
                        ], false);
                        return $this->getGroupsList();
                    }
                }
            }
        }
        return false;
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
    function addGroupToProject($projectID, $group_id) {
        if ($this->userCanEditProject($projectID)) {
            $fields_obj = [
                "project_id" => $projectID,
                "group_id" => $group_id,
                "field_json_props" => "",
                "status" => 0,
            ];
            $fields_obj['group_idx'] = $this->getProjectGroupIdx($projectID) + 1;
            $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);
        }
        return $this->getProjectsActivity($projectID);
    }
    function getGroupStatus($activityID) {
        return $this->db_object_project->selectFieldFromTable($this->tProjectsActivity["tableName"], ["id" => $activityID], "status");
    }
    function changeProjectActivity($projectID, $groupID, $group_fields) {
        if (isset($group_fields['group_idx']) && $this->userCanEditProject($projectID)) {
            $current_group_idx = $this->getProjectGroupIdx($projectID, $groupID);
            $group_id_arr = [];
            $nop = false;
            $plus = true;
            $sql = "SELECT `id`, `group_idx` FROM `projects_activity` WHERE `project_id`=:param1 AND ";
            if ($group_fields['group_idx'] < $current_group_idx) {
                $sql .= " `group_idx`>=:param3 AND `group_idx`<:param2";
            } elseif ($group_fields['group_idx'] > $current_group_idx) {
                $sql .= " `group_idx`>:param2 AND `group_idx`<=:param3";
                $plus = false;
            } else {
                $nop = true;
            }
            $sql .= " ORDER BY `id`";
            if (!$nop && ($table_res = $this->db_object_project->getSQL($sql, ["param1" => $projectID, "param2" => $current_group_idx, "param3" => $group_fields['group_idx']]))) {
                $sql_upd = "UPDATE `projects_activity` SET `group_idx`=:param1 WHERE `id`=:param2";
                foreach ($table_res as $result) {
                    $new_idx = ($plus) ? $result['group_idx'] + 1 : $result['group_idx'] - 1;
                    $this->db_object_project->modSQL($sql_upd , [
                        'param1' => $new_idx,
                        'param2' => $result['id']
                    ], false);
                }
                $sql_upd = "UPDATE `projects_activity` SET `group_idx`=:param1 WHERE `project_id`=:param2 AND `group_id`=:param3";
                $this->db_object_project->modSQL($sql_upd , [
                    'param1' => $group_fields['group_idx'],
                    'param2' => $projectID,
                    'param3' => $groupID,
                ], false);
            }
        } elseif ($this->userCanEditActivity($projectID, $groupID) && isset($group_fields['field_json_props'])) {
            $sql = "SELECT `id`, `status` FROM `projects_activity` WHERE `project_id`=:param1 AND `group_id=:param2`";
            $currentTimestamp = time();
            $start_date = date('Y-m-d H:i:s', $currentTimestamp);
            $last_activity_date = date('Y-m-d H:i:s', $currentTimestamp);
            $end_date = date('Y-m-d H:i:s', $currentTimestamp);
            
            if ($table_res = $this->db_object_project->getSQL($sql, ["param1" => $projectID, "param2" => $groupID])) {
                if ($table_res[0]['status'] == 0) {
                    $sql_params = [
                        'param1' => $group_fields['field_json_props'],
                        'param2' => $start_date,
                        'param3' => $last_activity_date,
                        'param4' => $table_res[0]['id']
                    ];
                    $sql_upd = "UPDATE `" .$this->tProjectsActivity["tableName"]. "` SET `field_json_props`=:param1, `start_date`=:param2,`last_activity_date`=:param3,`status`=1 WHERE `id`=:param4";
                    $this->db_object_project->modSQL($sql_upd , $sql_params, false);
                } elseif ($table_res[0]['status'] == 1) {
                    $sql_params = [
                        'param1' => $group_fields['field_json_props'],
                        'param3' => $last_activity_date,
                        'param4' => $table_res[0]['id']
                    ];
                    $sql_upd = "UPDATE `" .$this->tProjectsActivity["tableName"]. "` SET `field_json_props`=:param1,`last_activity_date`=:param3 WHERE `id`=:param4";
                    $this->db_object_project->modSQL($sql_upd , $sql_params, false);
                }
            }
        }
    }
}


