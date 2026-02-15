<?php
namespace myProjectUtils;

require_once 'classDatabase.php';
require_once 'classHelper.php';

class ProjectUtils extends \helperUtils\helperUtils {
    private $db_object_project = null;
    private $tProjects = [
        "tableName" => "projects",
        "fields" => [
            "id", "name", "number", "description", "user_id", "start_date", "end_date", "last_activity_date", "status"
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
            "id", "project_id", "group_id", "group_idx", "field_json_props", "element", "activity", "start_date", "end_date", "last_activity_date", "status"
        ]
    ];
    private $tUsers = [
        "tableName" => "users",
        "fields" => [
            "id", "user_name"
        ]
    ];
    private $tProjectsLog = [
        "tableName" => "projects_log",
        "fields" => [
            "id", "activity_date", "project_id", "activity_text"
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
    function getOwnerProjectID($projectID) {
        return $this->db_object_project->getOwnerProjectID($projectID);
    }
    function userCanEditProject($projectID) {
        return ($this->getOwnerProjectID($projectID) === $this->userID || $this->getRootAccess());
    }
    function userCanEditActivity($projectID, $groupID) {
        // $activity_status = $this->db_object_project->selectFieldFromTable($this->tProjectsActivity["tableName"], ["project_id" => $projectID, "group_id" => $groupID], "status");
        // return (($activity_status === 0 || $activity_status === 1) && ($this->userInGroup($groupID) || $this->getRootAccess()));
        return ($this->userInGroup($groupID) || $this->getRootAccess());
    }
    function userInGroup($groupID) {
        $users_list = json_decode($this->db_object_project->selectFieldFromTable($this->tGroups["tableName"], ["id" => $groupID], "users"), true);
        if ($users_list !== null && count($users_list) > 0) {
            if (in_array($this->userName, $users_list)) {
                return true;
            }
        }
        return false;
    }
    function addProject($p_name, $p_number, $p_description) {
        $currentTimestamp = time();
        $fields_obj = [
            "name" => $p_name,
            "number" => $p_number,
            "description" => $p_description,
            "user_id" => $this->userID,
            "start_date" => date('Y-m-d H:i:s', $currentTimestamp),
            "last_activity_date" => date('Y-m-d H:i:s', $currentTimestamp),
            "status" => 0,
        ];
        $new_project_id = $this->db_object_project->addObjectToTable($this->tProjects["tableName"], $fields_obj);
        return $this->getProjectsActivity($new_project_id);
    }
    function changeProject($p_id, $p_name, $p_number, $p_description) {
        $fields_obj = [
            "id" => $p_id,
            "name" => $p_name,
            "number" => $p_number,
            "description" => $p_description,
        ];
        $sql_upd = "UPDATE `" . $this->tProjects["tableName"] . "` SET `name`=:name, `number`=:number, `description`=:description WHERE `id`=:id";
        $this->db_object_project->modSQL($sql_upd , $fields_obj, false);
        return $this->getProjectsActivity($p_id);
    }
    function removeProject($projectID) {
        if ($this->userCanEditProject($projectID)) {
            if ($this->db_object_project->selectObjectFromTable($this->tProjectsActivity["tableName"], ["status" => 2], $this->tProjectsActivity["fields"], [])) {
                return false;
            }
            if ($this->db_object_project->removeObjectFromTableFilter($this->tProjectsActivity["tableName"], ["project_id" => $projectID])) {
                // , "status" => 0
                $projectName = $this->getProjectName($projectID);
                if ($this->db_object_project->removeObjectFromTable($this->tProjects["tableName"], $projectID)) {
                    $this->logProject($projectID, 'Project `' . $projectName . '` was removed by user `' . $this->userName . '`');
                  return true;
                }
            }
        }
        return false;
    }
    private function logProject($projectID, $activityText) {
        $this->db_object_project->runInsertSQL($this->tProjectsLog["tableName"], [
            'activity_date' => date('Y-m-d H:i:s', time()),
            'project_id'    => $projectID,
            'activity_text' => $activityText
        ]);
    }

    function removeGroup($groupID) {
        if ($this->getRootAccess()) {
            return $this->db_object_project->removeObjectFromTable($this->tGroups["tableName"], $groupID);
        }
        return false;
    }
    // function removeGroupFromProject($activityID) {
    //     if ($this->userCanEditProject($projectID)) {
    //         if ($this->getGroupStatus($activityID) !== 0) {
    //             return false;
    //         }
    //         return $this->db_object_project->removeObjectFromTable($this->tProjectsActivity["tableName"], $activityID);
    //     }
    //     return false;
    // }

    function addGroupToProject($projectID, $groupID) {
        if ($this->userCanEditProject($projectID)) {
            $fields_obj = [
                "project_id" => $projectID,
                "field_json_props" => "",
                "status" => 0,
            ];
            $fields_obj['group_idx'] = $this->getProjectGroupIdx($projectID) + 1;
            if (is_array($groupID)) {
                foreach ($groupID as $value) {
                    $fields_obj['group_id'] = $value;
                    $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);
                    $fields_obj['group_idx']++;
                }
            } else {
                $this->db_object_project->addObjectToTable($this->tProjectsActivity["tableName"], $fields_obj);
            }
            // $this->logProject($projectID, 'group `' . $this->getGroupName($groupID) . '` was added to project `' . $this->getProjectName($projectID) . '` by user `' . $this->userName . '`');
            return $this->getProjectsActivity($projectID);
        }
        return false;
    }

    function removeGroupFromProjectByGroup($projectID, $groupID) {
        if ($this->userCanEditProject($projectID)) {
            $this->db_object_project->removeObjectFromTableFilter($this->tProjectsActivity["tableName"], ["project_id" => $projectID, "group_id" => $groupID]);
            $this->logProject($projectID, 'group `' . $this->getGroupName($groupID) . '` was removed from project `' . $this->getProjectName($projectID) . '` by user `' . $this->userName . '`');
            return $this->getProjectsActivity($projectID);
        }
        return false;
    }

    function getUserID($userName) {
        $this->userID = $this->db_object_project->getUserID($userName);
        $this->userName = $userName;
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
    function getProjectsList($filters = []) {
        // return $this->db_object_project->selectObjectFromTable($this->tProjects["tableName"], $filters, $this->tProjects["fields"], ["number"]);
        // $sql = 'SELECT '
        $selected_fields = $this->backquoteForTables([
            // $this->tProjects["tableName"] => $this->tProjects["fields"],
            // $this->tUsers["tableName"] => ["user_name"]
            "t1" => $this->tProjects["fields"],
            "t2" => ["user_name"]
        ]);
        $filtered_arr = [];
        if (count($filters) < 1) {
            $filtered_arr['filter'] = 't1.`id`<>:param1';
            $filtered_arr['params'] = [
                'param1'	=> 0
            ];
		} else {
			$filtered_arr = $this->db_object_project->createFilterDB('t1', $filters, "AND");
		}
        $sql = "SELECT " . $selected_fields ." FROM " . $this->tProjects["tableName"]. " AS t1," . $this->tUsers["tableName"] . " AS t2 WHERE " . $filtered_arr['filter'] . " AND t1.`user_id`=t2.`id`";
        return $this->db_object_project->getSQL($sql, $filtered_arr['params']);
    }
    function getProjectsActivity($projectID = 0) {
        $activiry_res = [
            "info" => [],
            "detail" => []
        ];
        $project_info = [];
        // if (!isset($projectID)) {
        //     return $activiry_res;
        // }
        if ($projectID === 0) {
            $project_info = $this->getProjectsList();
        } else {
            $project_info = $this->getProjectsList(["id" => $projectID]);
        }
        if ($project_info && count($project_info) > 0) {
            $project_activities = $this->db_object_project->selectObjectFromTable($this->tProjectsActivity["tableName"], ["project_id" => $project_info[0]['id']], $this->tProjectsActivity["fields"], ["group_idx"]);
            $activiry_res["info"] = $project_info[0];
            $activiry_res["detail"] = $project_activities;
        }
        return $activiry_res;
    }
    function getProjectsActivityByID($activityID) {
        $activity_res = [];
        $project_activities = $this->db_object_project->selectObjectFromTable($this->tProjectsActivity["tableName"], ["id" => $activityID], $this->tProjectsActivity["fields"], []);
        $activity_res["detail"] = $project_activities[0] ?? [];
        if (count($activity_res["detail"]) > 0) {
            $activity_res["detail"]["group_name"] = $this->getGroupName($activity_res["detail"]["group_id"]);
        }
        return $activity_res;
    }
    function getProjectsActivityAll() {
        $activiry_res = [];
        $project_info = $this->getProjectsList();
        $sql = "SELECT pa.`id`,pa.`project_id`,pa.`group_id`,pa.`group_idx`,pa.`start_date`,pa.`end_date`,pa.`last_activity_date`,pa.`status`, `groups`.`name` as group_name FROM `" . $this->tProjectsActivity["tableName"]. "` as pa, `groups` WHERE pa.`project_id`=:param1 AND pa.`group_id`=`groups`.id ORDER BY pa.`group_idx`";
        if ($project_info && count($project_info) > 0) {
            foreach ($project_info as $p_info) {
                $activity = ["info" => $p_info];
                $detail = [];
                $project_activities = $this->db_object_project->getSQL($sql, ["param1" => $p_info['id']]);
                foreach ($project_activities as $p_detail) {
                    $detail[] = $p_detail;
                }
                $activiry_res[] = [
                    "info" =>  $p_info,
                    "detail" => $detail
                ];
            }
        }
        return $activiry_res;
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

    function getGroupStatus($activityID) {
        return $this->db_object_project->selectFieldFromTable($this->tProjectsActivity["tableName"], ["id" => $activityID], "status");
    }
    private function getGroupName($groupID) {
        return $this->db_object_project->selectFieldFromTable($this->tGroups["tableName"], ["id" => $groupID], "name");
    }
    private function getProjectName($projectID) {
        return $this->db_object_project->selectFieldFromTable($this->tProjects["tableName"], ["id" => $projectID], "name");
    }
    function changeProjectActivity($projectID, $groupID, $group_fields, $element, $activity) {
        if (isset($group_fields['group_idx']) && $this->userCanEditProject($projectID)) {
            if ($group_fields['group_idx'] == 0) {
                $this->removeGroupFromProjectByGroup($projectID, $groupID);
                return $this->getProjectsActivity($projectID);
            }
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
            return $this->getProjectsActivity($projectID);
        } elseif ($this->userCanEditActivity($projectID, $groupID) && isset($group_fields['field_json_props'])) {
            $sql = "/*CHANGE_ACTIVITY*/ SELECT `id`,`start_date`,`status` FROM `" .$this->tProjectsActivity["tableName"]. "` WHERE `project_id`=:param1 AND `group_id`=:param2";
            $currentTimestamp = time();
            $start_date = date('Y-m-d H:i:s', $currentTimestamp);
            $last_activity_date = date('Y-m-d H:i:s', $currentTimestamp);
            $end_date = date('Y-m-d H:i:s', $currentTimestamp);
            $status = 2;
            if (isset($group_fields['target_date'])) {
                $start_date = $group_fields['target_date'];
                $last_activity_date = $group_fields['target_date'];
                $status = 1;
            }
            if ($table_res = $this->db_object_project->getSQL($sql, ["param1" => $projectID, "param2" => $groupID])) {
                if ($table_res[0]['status'] == 0 || $table_res[0]['status'] == 1) {
                    $sql_params = [
                        'param1' => json_encode($group_fields['field_json_props']),
                        'param2' => $start_date,
                        'param3' => $last_activity_date,
                        'param4' => $table_res[0]['id'],
                        'param5' => $element,
                        'param6' => $activity,
                        'param7' => $status
                    ];
                    $sql_upd = "UPDATE `" .$this->tProjectsActivity["tableName"]. "` SET `field_json_props`=:param1,`element`=:param5,`activity`=:param6,`start_date`=:param2,`last_activity_date`=:param3,`status`=:param7 WHERE `id`=:param4";
                    $this->db_object_project->modSQL($sql_upd , $sql_params, false);
                } elseif ($table_res[0]['status'] == 2) {
                    $start_date = $table_res[0]['start_date'];
                    if (count($group_fields['field_json_props']) == 0) {
                        $start_date = null;
                        $last_activity_date = null;
                        $end_date = null;
                        $status = 0;
                    }
                    $sql_params = [
                        'param1' => '',
                        'param2' => $start_date,
                        'param3' => $last_activity_date,
                        'param4' => $status,
                        'param5' => $table_res[0]['id'],
                        'param6' => '',
                        'param7' => ''
                    ];
                    $sql_upd = "UPDATE `" .$this->tProjectsActivity["tableName"]. "` SET `field_json_props`=:param1,`start_date`=:param2,`element`=:param7,`activity`=:param6,`last_activity_date`=:param3,`status`=:param4 WHERE `id`=:param5";
                    $this->db_object_project->modSQL($sql_upd , $sql_params, false);
                    $this->logProject($projectID, 'Group `' . $this->getGroupName($groupID) . '` was cleared in project `' . $this->getProjectName($projectID) . '` by user `' . $this->userName . '`');
                }
                return $this->getProjectsActivityByID($table_res[0]['id']);
            }
        }
        return false;
    }
}


