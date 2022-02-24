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
	$projectID = false;
	unset($param_error_msg['answer']);
	error_log('Exception: ' . $e->getMessage());
	$param_error_msg['error'] = $e->getMessage();
}


if ($projectID !== false && $method !== 0)
{
	if (isset($_SESSION['logged_user']) && $_SESSION['logged_user']) {
		$currentUser = $_SESSION['logged_user'];
		$rights = $db_object->getRights($_SESSION['logged_user'], 'dummypass', true);
	}
	
	$section = $params['section'] ?? '';
	$accessType = $db_object->getAccessType($rights, $section);

	if ($method === 'signIn') {
		$kanboardUserName = trim($params['userName'] ?? 'defaultUser');
		$kanboardUserPass = trim($params['password'] ?? '');
		if (strlen($kanboardUserName) && strlen($kanboardUserPass)) {
			$rights = $db_object->getRights($kanboardUserName, $kanboardUserPass);
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
					if (/* ($task['creator_id'] != $userID) || */
						($shownedColumnID != $task['column_id']) || 
						((int)$task['date_completed'] !== 0)) {
						continue;
					}
					$projectName = $kanboard->getTaskProjectName($task['id']);
					$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
					$task_version = $taskMetadata['result']['version'] ?? false;
					$task_origin_id = $taskMetadata['result']['origintask'] ?? 0;
					if ($task_origin_id == 0 || ($task_origin_id === $task['id'])){
						$task_version = false;
					}
					$taskFiles = $kanboard->callKanboardAPI('getAllTaskFiles', [
							'task_id'	=> $task['id'],
							]);
					$param_error_msg['answer'][] = [
						'id'			=> (int)$task['id'],
						'creator_id'	=> (int)$task['creator_id'],
						'date_creation'	=> (int)$task['date_creation'],
						'date_completed'=> (int)$task['date_completed'],
						'description'	=> nl2br($task['description'], FALSE),
						'title'			=> $task['title']. (($task_version != false) ? '_v'.$task_version : ''),
						'project_name'	=> $projectName,
						'files'			=> array_map("taskFilesMapper", $taskFiles['result'] ?? []),
					];
				}
			}
		}
		elseif ($method === 'createTask' && $params !== 0 && (trim($params['title'] ?? "") != ''))
		{
			$taskCreator = trim($params['creator'] ?? "");
			// имя создателя приходит от фронта
			// if ($currentUser !== 'defaultUser' || isset($params['section']) && $section === 'status') {
			// 	$taskCreator = $currentUser;
			// }
			if ($accessType !== false)
			{
				$kanboardUserID = $kanboard->getUserIDByName(trim($params['assignee_name'] ?? ""));
				$columnID = $kanboard->getColumnID($params['status'] ?? "");
				$date_ts = setDateStarted($params['date_started']);

				if (isset($params['version'])) {
					$task_version = (int)$kanboard->getMetadataField($params['id'], 'version');
					if ($task_version === 0) $task_version = 1;
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
				$task_title = ($task_version !== false && $task_origin_title !== null) ? $task_origin_title : (trim($params['title'] ?? ""));

				$arr_params = [
					'title'			=> $task_title,
					'project_id'	=> $projectID,
					'reference'		=> trim($params['reference'] ?? ""),
					'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".$taskCreator."\nOTL: ".(trim($params['OTL'] ?? "")),
					'creator_id'	=> $userID,
				];
				if ($kanboardUserID != 0) {
					$arr_params['owner_id'] = $kanboardUserID;
				}
				if ($columnID !== false) {
					$arr_params['column_id'] = $columnID;
				}
				if ($date_ts) {
					$arr_params['date_started']	= date('Y-m-d H:i', $date_ts);
				}
				
				$taskResult = $kanboard->callKanboardAPI($method, $arr_params);
				if (isset($taskResult['result']))
				{
					if(isset($params['projectName']) && trim($params['projectName']) !== '') {
						$kanboard->setTaskProjectName((int)$taskResult['result'], trim($params['projectName']));
					}
					$kanboard->setTaskMetadata((int)$taskResult['result'], 
						[
							"otl"		=> trim($params['OTL'] ?? ""),
							"oracle"	=> trim($params['oracle'] ?? ""),
							"capop"		=> trim($params['capop'] ?? ""),
							"creator"	=> $taskCreator,
							"version"	=> ($task_version !== false) ? $task_version : 1,
							"origintask"	=> ($task_version !== false) ? $params['id'] : (int)$taskResult['result'],
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
		elseif ($method === 'removeTask' && $accessType === 'admin' && $section === 'excel' && $params !== 0 && $params['id'] != 0)
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
		elseif ($method === 'updateTask' && $accessType !== false && $params !== 0 && $params['id'] != 0)
		{
			$pattern = '/_v\d+$/i';
			$title = preg_replace($pattern, '', trim($params['title'] ?? ""));
			$taskResult = $kanboard->callKanboardAPI($method, [
							'title'			=> $title,
							'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".(trim($params['creator'] ?? ""))."\nOTL: ".(trim($params['OTL'] ?? "")),
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
		elseif($method === 'updateTaskFull' && $accessType === 'admin' && $section === 'excel' && $params !== 0 && $params['id'] != 0)
		{
			$taskCreator = trim($params['creator'] ?? "");
			$kanboardUserID = $kanboard->getUserIDByName(trim($params['assignee_name'] ?? ""));
			$columnID = $kanboard->getColumnID($params['status'] ?? "");
			$date_ts = setDateStarted($params['date_started']);
			$pattern = '/_v\d+$/i';
			$title = preg_replace($pattern, '', trim($params['title'] ?? ""));
			$arr_params = [
				'id'	=> $params['id'],
				'title'	=> $title,
				'reference'		=> trim($params['reference'] ?? ""),
				'description'	=> (trim($params['description'] ?? ""))."\nSubmitted by: ".$taskCreator."\nOTL: ".(trim($params['OTL'] ?? "")),
			];
			if ($date_ts) {
				$arr_params['date_started']	= date('Y-m-d H:i', $date_ts);
			}
			if ($kanboardUserID != 0) {
				$arr_params['owner_id'] = $kanboardUserID;
			}
			$taskResult = $kanboard->callKanboardAPI('updateTask', $arr_params);
			if(isset($taskResult['result']) && $taskResult['result']) {
				if ($columnID !== false) {
					$taskResult = $kanboard->callKanboardAPI('moveTaskPosition', [
						'project_id'	=> $projectID,
						'task_id'		=> $params['id'],
						'column_id'		=> $columnID,
						'position'		=> 10000,
						'swimlane_id' 	=> 1
					]);
				}	
				$taskResult = $kanboard->callKanboardAPI('saveTaskMetadata', [
					$params['id'], [
						"otl"		=> trim($params['OTL'] ?? ""),
						"oracle"	=> trim($params['oracle'] ?? ""),
						"capop"		=> trim($params['capop'] ?? ""),
						"creator"	=> $taskCreator,
						// "user_name" => trim($params['user_name'] ?? ""),
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
		elseif ($method === 'getAllTaskFiles' && $params !== 0 && $params['id'] != 0)
		{
			$param_error_msg['answer'] = $kanboard->getAllTaskFiles(trim($params['id']));
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
					$param_error_msg['answer'] = $kanboard->getAllTaskFiles(trim($params['id']));
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
				$param_error_msg['answer'] = $kanboard->getAllTaskFiles($taskID);
			}
		}
		elseif($method === 'getBoard')
		{
			if (($section === 'excel' || $section === 'status' || $section === 'statistics') && 
				($accessType === 'user' || $accessType === 'admin')) {
				$taskResult = $kanboard->callKanboardAPI('getBoard', [
				$projectID,
			]);
				if (isset($taskResult['result']) && count($taskResult['result'])) {
					$assignee_name = '';
					$all_column = false;
					if ($params !== 0 && $params['status'] == 'all') {
						$all_column = true;
					}
					foreach ($taskResult['result'][0]['columns'] as $key => $column) {
						if($shownedColumnID != $column['id'] && !$all_column) continue;
						foreach ($column['tasks'] as $key => $task) {
							if ($task['is_active'] == 1 && ($section !== 'status' || $task['creator_id'] == $userID)) {
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								// if ($accessType === 'user' && ($taskMetadata['result']['creator'] ?? '') !== $currentUser)
								// {
								// 	continue;
								// }
								$task_version = $taskMetadata['result']['version'] ?? false;
								$task_origin_id = $taskMetadata['result']['origintask'] ?? 0;
								$projectName = $kanboard->getTaskProjectName($task['id']);
								if($assignee_name === '') {
									$assignee_name = $task['assignee_username'] ?? 'not assigned';
								}
								if ($task_origin_id == 0 || ($task_origin_id === $task['id'])){
									$task_version = false;
								}
								$param_error_msg['answer'][] = [
									'id'			=> (int)$task['id'],
									'date_due'		=> (int)$task['date_due'],
									'date_creation'	=> (int)$task['date_creation'],
									'date_started'	=> (int)$task['date_started'],
									'title'			=> $task['title']. (($task_version != false) ? '_v'.$task_version : ''),
									'status'		=> $column['title'],
									'reference'		=> $task['reference'],
									'description'	=> $task['description'],
									'project_name'	=> $projectName,
									'assignee_name'	=> $assignee_name,
									'fields'		=> $kanboard->getMetadataFields($taskMetadata['result']),
									'editable'		=> ($accessType === 'user') ? 0 : 1,
								];
								$assignee_name = '';
							}
						}
					}
				}
			}
			
		}
		elseif( ($method === 'getTagsByProject' || $method === 'getAssignableUsers'))
		{
			if ($method === 'getTagsByProject') {
				$field_name = 'name';
			} else {
				$field_name = 'user';
			}
			$result = $kanboard->callKanboardAPI($method, [$projectID]);

			if (isset($result['result']) && count($result['result'])) {
				foreach($result['result'] as $result_object) {
					if ($field_name == 'user') {
						$param_error_msg['answer'][] = $result_object;
					} else {
						$param_error_msg['answer'][] = $result_object[$field_name];
					}
				}
			}
		}
		elseif($method === 'getColumns')
		{
			foreach(array_values($kanboard->getColumnsNames()) as $result_object) {
				$param_error_msg['answer'][] = $result_object;
			}
		}
		elseif ($method === 'doDataExport' && $params !== 0 && ($section === 'excel' || $section === 'statistics') && $accessType !== false) 
		{
			$all_column = false;
			if (isset($params['status']) && $params['status'] === 'all') {
				$all_column = true;
			}

			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
			$spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);

			$taskResult = $kanboard->callKanboardAPI('getBoard', [
						$projectID,
					]);

			if ($section === 'statistics') {
				try
				{
					if (isset($taskResult['result']) && count($taskResult['result'])) {
						$sheet->fromArray([
								'Project Name',
								'Date created',
								'OTL',
								'Tilte',
								'Ticket creator',
							], 
							NULL, 
							'A1');
						$rowExcel = 2;
						foreach ($taskResult['result'][0]['columns'] as $key => $column) {
							if ($shownedColumnID !== $column['id'] && !$all_column) continue;
							foreach ($column['tasks'] as $key => $task) {
								if ($task['is_active'] != 1 /*  || ($task['creator_id'] != $userID) */) continue;
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								$fieldsMetadata = $kanboard->getMetadataFields($taskMetadata['result']);
								$sheet->fromArray([
									$kanboard->getTaskProjectName($task['id']),
									$task['date_creation'] > 0 ? date("Y-m-d", $task['date_creation']) : '',
									$fieldsMetadata['otl'],
									$task['title'],
									$fieldsMetadata['creator'],
								], 
								NULL, 
								'A'.$rowExcel);
								$rowExcel++;
							}
						}
					}
					$writer = new Xls($spreadsheet);
					$writer->save($filename);
					$xls_output = TRUE;
				}
				catch (Exception $e) 
				{
					error_log($e->getMessage());
				}
			} elseif ($section === 'excel') {
				$days = 1;
				if(isset($params['days'])) {
					$days = $params['days'];
				}
				$dayObj = new DateTime();
				$today = new DateTime();
				$prevday = new DateTime();
				$prevday->setTime(0,0,0);
				$today->setTime(23,59,59);
				if ($days == '7' || $days == '14') {
					$dayOfWeek = $prevday->format('w');
					$prevday->sub(new DateInterval('P'.$dayOfWeek.'D'));
					$dayStart = $prevday->getTimestamp();
					$today->add(new DateInterval('P'.($days - $dayOfWeek - 1).'D'));
					$dayEnd = $today->getTimestamp();
				} elseif ($days == '31' || $days == '62') {
					$prevday->setDate((int)$prevday->format('Y'), (int)$prevday->format('n'), 1);
					$dayStart = $prevday->getTimestamp();
					$today->setDate((int)$prevday->format('Y'), (int)$prevday->format('n') + ($days / 31), 0);
					$dayEnd = $today->getTimestamp();
				} elseif ($days == '365') {
					$prevday->setDate((int)$prevday->format('Y'), 1, 1);
					$dayStart = $prevday->getTimestamp();
					$today->setDate((int)$prevday->format('Y'), 12, 31);
					$dayEnd = $today->getTimestamp();
				} else {
					$dayObj->setTime(23,59,59);
					$dayEnd = $dayObj->getTimestamp();
					if ($days >=0) {
						$dayObj->sub(new DateInterval('P'.$days.'D'));
					} else {
						$dayEnd -= $days * 24 * 3600;
						$dayObj->add(new DateInterval('P'.abs($days).'D'));
					}
					$dayObj->setTime(0,0,0);
					$dayStart = $dayObj->getTimestamp();
				}
				try
				{
					if (isset($taskResult['result']) && count($taskResult['result'])) {
						$sheet->fromArray([
								'Date started',
								'Assigned',
								'Title',
								'Reference',
								'Capex/Opex',
								'Oracle',
							], 
							NULL, 
							'A1');
						$rowExcel = 2;
						foreach ($taskResult['result'][0]['columns'] as $key => $column) {
							if ($shownedColumnID != $column['id'] && !$all_column) continue;
							foreach ($column['tasks'] as $key => $task) {
								$task_started = (int)$task['date_started'];
								if($task['is_active'] == 1 /* && ($task['creator_id'] == $userID) */ && ($task_started > $dayStart && $task_started < $dayEnd || $task_started == 0)) {
									$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
									// if ($accessType === 'user' && ($taskMetadata['result']['creator'] ?? '') !== $currentUser)
									// {
									// 	continue;
									// }
									$fieldsMetadata = $kanboard->getMetadataFields($taskMetadata['result']);
									$sheet->fromArray([
										$task['date_started'] > 0 ? date("Y-m-d", $task['date_started']) : '',
										$task['assignee_username'] ?? 'not assigned',
										$task['title'],
										$task['reference'],
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