<?php
require_once 'config.php';
require_once 'classKanboard.php';

$out_res = [];
$param_error_msg['answer'] = [];

$paramJSON = json_decode(file_get_contents("php://input"), TRUE);
$method = $paramJSON['method'] ?? $_REQUEST['method'] ?? 0;
$params = $paramJSON['params'] ?? $_REQUEST ?? 0;
if ($method !== 0)
{
	try
	{
		$kanboard = new Kanboard;
		$projectID = $kanboard->projectID;
		$userID = $kanboard->userID;
		$shownedColumnID = $kanboard->shownedColumnID;
	}
	catch (Exception $e) {
		$projectID = FALSE;
		unset($param_error_msg['answer']);
		error_log('Exception: ' . $e->getMessage());
		$param_error_msg['error'] = $e->getMessage();
	}
	if ($projectID) 
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
						// ($task['date_moved'] - $task['date_creation'] > 5) ||
						($shownedColumnID != $task['column_id']) || 
						((int)$task['date_completed'] !== 0)) {
						continue;
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
						'title'			=> $task['title'],
						'files'			=> array_map($taskFilesMapper, $taskFiles['result']),
					];
				}
			}
		}
		elseif($method === 'createTask' && $params !== 0)
		{
			$taskResult = $kanboard->callKanboardAPI($method, [
							'project_id'	=> $projectID,
							'title'			=> trim($params['title']) ?? "",
							'description'	=> (trim($params['description']) ?? "")."\nSubmitted by: ".(trim($params['creator']) ?? "")."\nOTL: ".(trim($params['OTL']) ?? ""),
							'date_started'	=> date('Y-m-d H:i'),
							'creator_id'	=> $userID,
							]);
			if (isset($taskResult['result']))
			{
				$param_error_msg['answer'] = [
					'id'	=> (int)$taskResult['result'],
				];
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
					'id'	=> 0,
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
				$param_error_msg['answer'] = [
					'id'	=> (int)$params['id'],
				];
			}
		}
		elseif($method === 'updateTaskFull' && $params !== 0 && $params['id'] != 0)
		{
			$taskResult = $kanboard->callKanboardAPI('updateTask', [
				'id'	=> $params['id'],
				'description'	=> (trim($params['description']) ?? ""),
				'date_due'		=> $params['date_due'],
			]);
			if(isset($taskResult['result']) && $taskResult['result']) {
				$taskResult = $kanboard->callKanboardAPI('saveTaskMetadata', [
              		$params['id'], [
                		"ticket"	=> (trim($params['ticket']) ?? ""),
               			"capop"		=> (trim($params['capop']) ?? ""),
                		"oracle"	=> (trim($params['oracle']) ?? ""),
              		]
            	]);
				if (isset($taskResult['result']))
				{
					$param_error_msg['answer'] = [
						'id'	=> (int)$params['id'],
					];
				}
			}
			if(isset($params['user_name']) && trim($params['user_name']) !== '') {
				$taskResult = $kanboard->callKanboardAPI('setTaskTags', [
					$projectID,
					$params['id'],
					[trim($params['user_name'])],
				]);
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
			$taskResult = $kanboard->callKanboardAPI('getBoard', [
				$projectID,
			]);
			if (isset($taskResult['result']) && count($taskResult['result'])) {
				$assignee_name = '';
				foreach ($taskResult['result'][0]['columns'] as $key => $column) {
					if($shownedColumnID == $column['id']) {
						foreach ($column['tasks'] as $key => $task) {
							if($task['is_active'] == 1) {
								$taskMetadata = $kanboard->callKanboardAPI('getTaskMetadata', [$task['id']]);
								$taskTags = $kanboard->callKanboardAPI('getTaskTags', [$task['id']]);
								if (isset($taskTags['result']) && count($taskTags['result'])) {
									$assignee_name = $kanboard->getUserNameFromTag($taskTags['result']);
								}
								if($assignee_name === '') {
									$assignee_name = $task['assignee_username'];
								}
								$param_error_msg['answer'][] = [
									'id'			=> (int)$task['id'],
									'date_due'		=> (int)$task['date_due'],
									'title'			=> $task['title'],
									'description'	=> $task['description'],
									'assignee_name'	=> $assignee_name,
									'fields'		=> $kanboard->getMetadataFields($taskMetadata['result']),
								];
								$assignee_name = '';
							}
						}
						break;
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
	}
	
}
if ($projectID) {
	$out_res = ['success' => $param_error_msg];	
}
else {
	$out_res = ['error' => $param_error_msg];	
}

header('Content-type: application/json');
echo json_encode($out_res);
?>