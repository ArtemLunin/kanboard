<?php
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );

require_once 'config.php';
require_once 'db_conf.php';
require_once 'classDatabase.php';
require_once 'classKanboard.php';

$out_res = [];
$param_error_msg['answer'] = [];

$xls_output = false;
$accessType = false;
$filename = tempnam(sys_get_temp_dir(), 'xls');

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$params = $paramJSON['params'] ?? $_REQUEST ?? 0;


$db_object = new mySQLDatabaseUtils\databaseUtils();

try
{
	$kanboard = new Kanboard;
	$projectID = $kanboard->projectID;
	$userID = $kanboard->userID;
	$shownedColumnID = $kanboard->shownedColumnID;
	$rights = $db_object->initialRights;
	$currentUser = 'defaultUser';
}
catch (Exception $e) {
	$projectID = FALSE;
	unset($param_error_msg['answer']);
	error_log('Exception: ' . $e->getMessage());
	$param_error_msg['error'] = $e->getMessage();
}

if (isset($_SESSION['logged_user']) && $_SESSION['logged_user']) {
	$currentUser = $_SESSION['logged_user'];
	$rights = $db_object->getRigths($_SESSION['logged_user'], 'dummypass', true);
}

$accessType = $db_object->getAccessType($rights, $params['section'] ?? 'null');


if ($method !== 0)
{
	if ($method === 'signIn') {
		$kanboardUserName = trim($params['userName'] ?? 'defaultUser');
		$kanboardUserPass = trim($params['password'] ?? '');
		if (strlen($kanboardUserName) && strlen($kanboardUserPass)) {
			$rights = $db_object->getRigths($kanboardUserName, $kanboardUserPass);
			if ($rights) {
				$_SESSION['logged_user'] = $kanboardUserName;
				$currentUser = $kanboardUserName;
			}
		}
		$param_error_msg['answer'] = [
			'user' => $kanboardUserName,
			'rights' => $rights,
		];
	} elseif ($method === 'logout') {
		$_SESSION = array();
		$param_error_msg['answer'] = [
			'logout' => true,
		];
	} elseif ($method === 'getRights') {
		$param_error_msg['answer'] = [
			'user'	 => $currentUser,
			'rights' => $rights,
		];
	} elseif ($method === 'addUser' && $params !== 0 && strlen(trim($params['userName'])) > 2 && strlen($params['password']) > 2) {
		$param_error_msg['answer'] = $db_object->addUser(trim($params['userName']), $params['password']);
	} elseif ($method === 'modUser' && $params !== 0 && strlen(trim($params['userName'])) > 2 && strlen($params['password']) > 2) {
		$param_error_msg['answer'] = $db_object->modUser(trim($params['userName']), $params['password']);
	} elseif ($method === 'delUser' && $params !== 0 && strlen(trim($params['userName'])) > 2) {
		$param_error_msg['answer'] = $db_object->delUser(trim($params['userName']));
	} elseif ($method === 'setRights' && $params !== 0 && strlen(trim($params['userName'])) > 2 && $params['rights']) {
		// $new_rights = json_decode($params['rights'], true);
		if (isset($params['rights'][0]['pageName'])) {
			$param_error_msg['answer'] = $db_object->setRights(trim($params['userName']), $params['rights']);
		}
	} elseif ($method === 'getKanboardUsers') {
		$param_error_msg['answer'] = $db_object->getKanboardUsers();
	}
	elseif ($projectID) 
	{
		if ($method === 'getAllTasks')
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
							'project_id'	=> $projectID,
							'status_id'		=> 1,
							]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				foreach ($taskResult['result'] as $key => $task) {
					if (($task['creator_id'] != $userID) ||
						($shownedColumnID != $task['column_id']) || 
						((int)$task['date_completed'] !== 0)) {
						continue;
					}
					$projectName = $kanboard->getTaskProjectName($task['id']);
					$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
							'task_id'	=> $task['id'],
							]);
					$param_error_msg['answer'][] = [
						'id'			=> (int)$task['id'],
						'creator_id'	=> (int)$task['creator_id'],
						'date_creation'	=> (int)$task['date_creation'],
						'date_completed'=> (int)$task['date_completed'],
						'description'	=> nl2br($task['description'], FALSE),
						'title'			=> $task['title'],
						'project_name'	=> $projectName,
						'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
					];
				}
			}
		}
		elseif($method === 'createTask' && $params !== 0)
		{
			$taskCreator = trim($params['creator'] ?? "");
			if (isset($params['section']) && $params['section'] == 'status') {
				$taskCreator = $currentUser;
			}
			if ($accessType != false)
			{
				if (isset($params['version'])) {
					$task_version = (int)$kanboard->getMetadataField($params['id'], 'version');
					$task_version++;
					$task_origin = $kanboard->fieldsTask($params['id'], true, false);
					$task_origin_title = $task_origin['title'] ?? null;
					$kanboard->setTaskMetadata($params['id'], 
						[
							"version"	=> $task_version,
						]);
				} else {
					$task_version = false;
				}
				// $db_object->errorLog('origin:'.$task_origin_title.', id:'.$params['id'].', version:'.$task_version);
				$task_title = ($task_version !== false && $task_origin_title !== null) ? $task_origin_title : (trim($params['title'] ?? ""));
				$taskResult = $kanboard->callKanboardAPI($method, [
					'project_id'	=> $projectID,
					'title'			=> $task_title,
					'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".$taskCreator."\nOTL: ".(trim($params['OTL'] ?? "")),
					'date_started'	=> date('Y-m-d H:i'),
					'creator_id'	=> $userID,
					]);
				if (isset($taskResult['result']))
				{
					if(isset($params['projectName']) && trim($params['projectName']) !== '') {
						$kanboard->setTaskProjectName((int)$taskResult['result'], trim($params['projectName']));
					}
					$kanboard->setTaskMetadata((int)$taskResult['result'], 
						[
							"otl"		=> trim($params['OTL'] ?? ""),
							"creator"	=> $taskCreator,
							"version"	=> ($task_version !== false) ? $task_version : 0,
							"origintask"	=> ($task_version !== false) ? $params['id'] : 0,
						]);
					$task_out = $kanboard->fieldsTask($taskResult['result'], true, $task_version);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$taskResult['result']]);
					$projectName = $kanboard->getTaskProjectName($taskResult['result']);
					$param_error_msg['answer'] = $task_out + 
						['fields'	=> $kanboard->getMetadataFields($taskMetadata['result'])] + 
						['project_name'	=> $projectName];
				}
			}
		}
		elseif ($method === 'removeTask' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
				'task_id'	=> $params['id'],
			]);
			if (isset($taskResult['result']))
			{
				$param_error_msg['answer'] = [
					'id'	=> (int)$params['id'],
				];
			}
		}
		elseif ($method === 'updateTask' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
							'title'			=> trim($params['title']) ?? "",
							'description'	=> (trim($params['description']) ?? "")."\nSubmitted by: ".(trim($params['creator']) ?? "")."\nOTL: ".(trim($params['OTL']) ?? ""),
							'id'	=> $params['id'],
							]);
			if (isset($taskResult['result']))
			{
				if(isset($params['projectName']) && trim($params['projectName']) !== '') {
					$kanboard->setTaskProjectName((int)$params['id'], trim($params['projectName']));
					$kanboard->setTaskMetadata((int)$params['id'], 
					[
						"otl"		=> (trim($params['OTL'] ?? "")),
						"creator"	=> (trim($params['creator'] ?? "")),
					]);
				}
				// getTask
				$task_out = $kanboard->fieldsTask($params['id'], true);
				$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$taskResult['result']]);
				$projectName = $kanboard->getTaskProjectName($params['id']);
				$param_error_msg['answer'] = $task_out + ['fields'		=> $kanboard->getMetadataFields($taskMetadata['result'])] + ['project_name'	=> $projectName];
			}
		} elseif ($method === 'getTask' && $params !== 0 && $params['id'] != 0) {

		}
		elseif($method === 'updateTaskFull' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI('updateTask', [
				'id'	=> $params['id'],
				'description'	=> (trim($params['description'] ?? "")),
				'date_due'		=> $params['date_due'],
			]);
			if(isset($taskResult['result']) && $taskResult['result']) {
				$taskResult = $kanboard->callKanboardAPI('saveTaskMetadata', [
              		$params['id'], [
                		"ticket"	=> (trim($params['ticket'] ?? "")),
               			"capop"		=> (trim($params['capop'] ?? "")),
                		"oracle"	=> (trim($params['oracle'] ?? "")),
						"user_name" => (trim($params['user_name'] ?? "")),
              		]
            	]);
				if (isset($taskResult['result']))
				{
					$task_out = $kanboard->fieldsTask($params['id'], false);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$params['id']]);
					$param_error_msg['answer'] = $task_out + ['fields'		=> $kanboard->getMetadataFields($taskMetadata['result'])];
				}
			}
		}
		elseif ($method === 'createTaskFile' && $params !== 0 && $params['id'] != 0)
		{
			$tmp = $_FILES['file']['tmp_name'];
			if (($tmp!='') && is_uploaded_file($tmp)) 
			{   
				$taskResult = $kanboard->callKanboardAPI($method, [
							$projectID,
							$params['id'],
							$_FILES['file']['name'],
							base64_encode(file_get_contents($tmp)),
							]);	
				if (isset($taskResult['result']))
				{
					$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
							'task_id'	=> $params['id'],
							]);
					$param_error_msg['answer'] = [
						'id'			=> (int)$params['id'],
						'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
					];
				}
			}
		}
		elseif ($method === 'removeTaskFile' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI('getTaskFile', [
							$params['id'],
							]);
			$taskID = $taskResult['result']['task_id'] ?? 0;
			$userTaskID = $taskResult['result']['user_id'] ?? 0;
			if ($taskID != 0)
			{
				$taskResult = $kanboard->callKanboardAPI($method, [
							$params['id'],
							]);
				$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
							'task_id'	=> $taskID,
							]);
				$param_error_msg['answer'] = [
					'id'			=> (int)$taskID,
					'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
				];
			}
		}
		elseif($method === 'getBoard')
		{
			if ($accessType === 'user' || $accessType === 'admin') {
				$taskResult = $kanboard->callKanboardAPI('getBoard', [
				$projectID,
			]);
				if (isset($taskResult['result']) && count($taskResult['result'])) {
					$column_names = $kanboard->getColumnsNames();
					$assignee_name = '';
					$all_column = false;
					if ($params !== 0 && $params['status'] == 'all') {
						$all_column = true;
					}
					foreach ($taskResult['result'][0]['columns'] as $key => $column) {
						if($shownedColumnID != $column['id'] && !$all_column) continue;
						foreach ($column['tasks'] as $key => $task) {
							if($task['is_active'] == 1) {
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								if ($accessType === 'user' && ($taskMetadata['result']['creator'] ?? '') !== $currentUser)
								{
									continue;
								}
								$task_version = $taskMetadata['result']['version'] ?? false;
								$task_origin_id = $taskMetadata['result']['origintask'] ?? 0;
								$projectName = $kanboard->getTaskProjectName($task['id']);
								if($assignee_name === '') {
									$assignee_name = $task['assignee_username'] ?? 'not assigned';
								}
								if ($task_origin_id == 0)
									$task_version = false;
								$param_error_msg['answer'][] = [
									'id'			=> (int)$task['id'],
									'date_due'		=> (int)$task['date_due'],
									'date_creation'	=> (int)$task['date_creation'],
									'title'			=> $task['title']. (($task_version != false) ? '_v'.$task_version : ''),
									'status'		=> $column_names[$column['id']] ?? 'undefined',
									'reference'		=> $task['reference'],
									'description'	=> $task['description'],
									'project_name'	=> $projectName,
									'assignee_name'	=> $assignee_name,
									'fields'		=> $kanboard->getMetadataFields($taskMetadata['result']),
								];
								$assignee_name = '';
							}
						}
					}
				}
			}
			
		}
		elseif($method === 'getAssignableUsers')
		{
			$taskResult = $kanboard->callKanboardAPI($method, [$projectID, false]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				foreach($taskResult['result'] as $user_name) {
					$param_error_msg['answer'][] = [
						'user_name' => $user_name,
					];
				}
			}
		}
		elseif($method === 'getTagsByProject')
		{
			$taskResult = $kanboard->callKanboardAPI($method, [$projectID]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				foreach($taskResult['result'] as $project) {
					$param_error_msg['answer'][] = [
						'project_name' => $project['name'],
					];
				}
			}
		}
		elseif($method === 'getTaskTags' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [$params['id']]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				foreach($taskResult['result'] as $user_name) {
					$param_error_msg['answer'][] = [
						'user_name' => $user_name,
					];
					// onfy first tag - aka username
					break;
				}
			}
		}
		elseif ($method == 'doDataExport' && $params !== 0 && $params['section'] != '') 
		{
			$days = 1;
			if(isset($params['days'])) {
				$days = $params['days'];
			}
			$dayObj = new DateTime();
			if ($days == 0) {
				$dayObj->sub(new DateInterval('P1D'));
			}
			$dayObj->setTime(0,0,0);
			$dayStart = $dayObj->getTimestamp();
			$dayObj->add(new DateInterval('P'.$days.'D'));
			$dayObj->setTime(23,59,59);
			$dayEnd = $dayObj->getTimestamp();
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
			try
			{
				$taskResult = $kanboard->callKanboardAPI('getBoard', [
					$projectID,
				]);
				if (isset($taskResult['result']) && count($taskResult['result'])) {
					$assignee_name = '';
					$all_column = false;
					if (isset($params['status']) && $params['status'] == 'all') {
						$all_column = true;
					}
					$sheet->fromArray([
							'Date due',
							'Assigned',
							'Description',
							'Reference',
							'Capex/Opex',
							'Oracle',
						], 
						NULL, 
						'A1');
					$rowExcel = 2;
					foreach ($taskResult['result'][0]['columns'] as $key => $column) {
						if($shownedColumnID != $column['id'] && !$all_column) continue;
						foreach ($column['tasks'] as $key => $task) {
							$task_date_due = (int)$task['date_due'];
							if($task['is_active'] == 1 && ($task_date_due >= $dayStart && $task_date_due < $dayEnd || $task_date_due == 0)) {
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								$fieldsMetadata = $kanboard->getMetadataFields($taskMetadata['result']);
								if($assignee_name === '') {
									$assignee_name = $task['assignee_username'] ?? 'not assigned';
								}
								$sheet->fromArray([
										$task['date_due'] > 0 ? date("Y-m-d", $task['date_due']) : '',
										$task['assignee_username'] ?? 'not assigned',
										$task['description'],
										$fieldsMetadata['ticket'],
										$fieldsMetadata['capop'],
										$fieldsMetadata['oracle'],
									], 
									NULL, 
									'A'.$rowExcel);
								$rowExcel++;
							}
						}
					}
				}
				$writer = new Xls($spreadsheet);
				$writer->save($filename);
				$xls_output = TRUE;
			}
			catch (Exception $e) 
			{}
		}
	}
	
}
if ($projectID) {
	$out_res = ['success' => $param_error_msg];	
}
else {
	$out_res = ['error' => $param_error_msg];	
}

if ($xls_output !== FALSE) {
	header("Content-Type: application/vnd.ms-excel; charset=utf-8");
	header("Content-Disposition: attachment; filename=export.xls");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	$handle = fopen($filename, "r");
	$contents = fread($handle, filesize($filename));
	echo $contents;
} else {
	header('Content-type: application/json');
	echo json_encode($out_res);
}
?>